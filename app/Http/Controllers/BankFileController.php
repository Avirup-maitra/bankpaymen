<?php

namespace App\Http\Controllers;

use App\Jobs\CollectBulkUploadStats;
use App\Jobs\ProcessBulkFiles;
use App\Models\BankFile;
use App\Models\BulkUploadSession;
use App\Services\BankFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankFileController extends Controller
{
    public function index()
    {
        $query = BankFile::latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('created_by', auth()->id());
        }

        $files = $query->paginate(20);
        $bulkSessions = BulkUploadSession::with('user')
            ->when(!auth()->user()->isAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
            ->latest()
            ->take(10)
            ->get();

        return view('bank-files.index', compact('files', 'bulkSessions'));
    }

    public function show(BankFile $bankFile)
    {
        if (!auth()->user()->isAdmin() && $bankFile->created_by !== auth()->id()) {
            abort(403);
        }
        return view('bank-files.show', compact('bankFile'));
    }

    public function create()
    {
        return view('bank-files.create');
    }

    public function bulkIndex(Request $request)
    {
        $sessions = BulkUploadSession::with('user')
            ->latest()
            ->paginate(10);

        $selectedSession = null;
        if ($request->query('session_id')) {
            $selectedSession = BulkUploadSession::where('session_id', $request->query('session_id'))->first();
        }

        if (! $selectedSession && $sessions->count() > 0) {
            $selectedSession = $sessions->first();
        }

        $rejectReasons = collect();
        $recentRejects = collect();

        if ($selectedSession) {
            $selectedSession->refreshStats();
            $selectedSession->refresh();

            $fileIds = $selectedSession->files()->pluck('id');

            if ($fileIds->isNotEmpty()) {
                $rejectReasons = DB::table('bank_transactions')
                    ->selectRaw("COALESCE(NULLIF(reject_reason, ''), 'Processing error') as reason, COUNT(*) as count")
                    ->whereIn('bank_file_id', $fileIds)
                    ->where('import_status', 'REJECTED')
                    ->groupBy('reason')
                    ->orderByDesc('count')
                    ->take(20)
                    ->get();

                $recentRejects = DB::table('bank_transactions')
                    ->join('bank_files', 'bank_transactions.bank_file_id', '=', 'bank_files.id')
                    ->select(
                        'bank_transactions.id',
                        'bank_transactions.row_number',
                        'bank_transactions.reject_reason',
                        'bank_transactions.amount',
                        'bank_transactions.payment_ref_no',
                        'bank_transactions.beneficiary_name',
                        'bank_files.original_filename'
                    )
                    ->whereIn('bank_transactions.bank_file_id', $fileIds)
                    ->where('bank_transactions.import_status', 'REJECTED')
                    ->latest('bank_transactions.id')
                    ->take(50)
                    ->get();
            }
        }

        return view('bulk-upload.index', compact('sessions', 'selectedSession', 'rejectReasons', 'recentRejects'));
    }

    public function store(Request $request, BankFileService $service)
    {
        $request->validate([
            'bank_type' => 'required|in:ICICI,SBI',
            'files' => ['required', 'array', 'min:1'],
            'files.*' => 'file|max:153600',
            'session_id' => ['nullable', 'string'],
            'total_expected_files' => ['nullable', 'integer', 'min:1'],
        ]);

        $bankType = $request->input('bank_type');
        $this->validateFileExtensions($request, $bankType);

        $bulkSession = null;
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            $bulkSession = BulkUploadSession::where('session_id', $sessionId)->firstOrFail();

            if ($bulkSession->user_id !== auth()->id()) {
                abort(403);
            }

            if ($bulkSession->bank_type !== $bankType) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bank_type' => 'All chunks in one bulk upload must use the same bank type.',
                ]);
            }
        } else {
            $sessionId = 'bulk_' . auth()->id() . '_' . time() . '_' . uniqid();
            $bulkSession = BulkUploadSession::create([
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
                'bank_type' => $bankType,
                'total_files_uploaded' => 0,
                'status' => 'QUEUED',
            ]);
        }

        $uploadedCount = 0;
        $failedFiles = [];
        $fileIds = [];

        foreach ($request->file('files') as $uploadedFile) {
            try {
                $bankFile = $service->handleUpload(
                    $uploadedFile,
                    $request->user(),
                    'MANUAL',
                    $bankType,
                    $bulkSession,
                    false
                );

                $uploadedCount++;
                $fileIds[] = $bankFile->id;
            } catch (\Exception $e) {
                $failedFiles[] = [
                    'name' => $uploadedFile->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $existingFileIds = $bulkSession->file_ids ?? [];
        $allFileIds = array_values(array_unique(array_merge($existingFileIds, $fileIds)));
        $previousUploadFailedFiles = $bulkSession->upload_failed_files ?? [];
        $allUploadFailedFiles = array_values(array_merge($previousUploadFailedFiles, $failedFiles));
        $totalExpected = (int) $request->input('total_expected_files', 0);
        $totalUploaded = count($allFileIds);

        $bulkSession->update([
            'total_files_uploaded' => max($totalUploaded + count($allUploadFailedFiles), $totalExpected ?: 0),
            'upload_failed_count' => count($allUploadFailedFiles),
            'upload_failed_files' => $allUploadFailedFiles,
            'file_ids' => $allFileIds,
            'status' => ($totalUploaded + count($allUploadFailedFiles)) > 0 ? 'QUEUED' : 'COMPLETED_WITH_ERRORS',
        ]);

        $batchSize = (int) config('bankfiles.processing.file_job_batch_size', 10);
        $batches = array_chunk($fileIds, $batchSize);

        foreach ($batches as $batch) {
            ProcessBulkFiles::dispatch($batch, $bulkSession->id);
        }

        CollectBulkUploadStats::dispatch(auth()->id(), $sessionId)->delay(now()->addSeconds(60));

        Cache::put("bulk_upload_{$sessionId}", [
            'files_to_process' => $totalUploaded,
            'files_uploaded' => $totalUploaded,
            'files_failed' => count($allUploadFailedFiles),
            'total_files' => max($totalUploaded + count($allUploadFailedFiles), $totalExpected ?: 0),
            'failed_files' => $failedFiles,
            'bank_type' => $bankType,
            'file_ids' => $allFileIds,
            'batch_size' => $batchSize,
            'total_batches' => count($batches),
            'timestamp' => now(),
        ], now()->addHours(24));

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'uploaded_count' => $uploadedCount,
                'accepted_so_far' => $totalUploaded,
                'failed_count' => count($failedFiles),
                'total_files' => max($totalUploaded + count($allUploadFailedFiles), $totalExpected ?: 0),
                'failed_files' => $failedFiles,
                'summary_url' => route('bank-files.summary', ['session_id' => $sessionId]),
                'message' => "Uploaded {$uploadedCount} file(s). Processing queued.",
            ]);
        }

        return redirect()->route('bank-files.summary', ['session_id' => $sessionId])
            ->with('success', "Uploaded {$uploadedCount} file(s). Processing queued.")
            ->with('failed_count', count($failedFiles))
            ->with('total_files', max($totalUploaded + count($allUploadFailedFiles), $totalExpected ?: 0));
    }

    public function summary(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('bank-files.index')->with('error', 'Invalid session');
        }

        $session = BulkUploadSession::where('session_id', $sessionId)->first();
        if (!$session) {
            return redirect()->route('bank-files.index')->with('error', 'Bulk upload session not found');
        }

        if (!auth()->user()->isAdmin() && $session->user_id !== auth()->id()) {
            abort(403);
        }

        $session->refreshStats();
        $session->refresh();

        $uploadData = [
            'files_uploaded' => $session->total_files_uploaded,
            'files_failed' => $session->files_failed,
            'bank_type' => $session->bank_type,
            'file_ids' => $session->file_ids ?? [],
        ];

        $stats = $session->toArray();

        return view('bank-files.summary', compact('sessionId', 'uploadData', 'stats'));
    }

    public function bulkUploadStats(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json(['error' => 'Session ID required'], 400);
        }

        $session = BulkUploadSession::where('session_id', $sessionId)->first();
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        if (!auth()->user()->isAdmin() && $session->user_id !== auth()->id()) {
            abort(403);
        }

        $session->refreshStats();
        $session->refresh();

        $fileProgresses = $session->files()
            ->select('id', 'original_filename', 'bank_type', 'status', 'total_rows', 'success_rows', 'rejected_rows', 'total_amount', 'error_summary', 'updated_at')
            ->orderByRaw("FIELD(status, 'PROCESSING', 'RECEIVED', 'REJECTED', 'PARTIAL', 'PROCESSED')")
            ->orderByDesc('id')
            ->get()
            ->map(function (BankFile $file) {
                $cached = Cache::get("bank_file_progress_{$file->id}", []);
                $percentage = $cached['percentage'] ?? match ($file->status) {
                    'RECEIVED' => 0,
                    'PROCESSING' => 50,
                    default => 100,
                };

                return [
                    'bank_file_id' => $file->id,
                    'filename' => $file->original_filename,
                    'bank_type' => $file->bank_type,
                    'status' => $file->status,
                    'stage' => $cached['stage'] ?? $this->stageForStatus($file->status),
                    'stage_message' => $cached['stage_message'] ?? $this->messageForStatus($file->status),
                    'total_rows' => $cached['total_rows'] ?? $file->total_rows,
                    'success_rows' => $cached['success_rows'] ?? $file->success_rows,
                    'rejected_rows' => $cached['rejected_rows'] ?? $file->rejected_rows,
                    'total_amount' => $cached['total_amount'] ?? $file->total_amount,
                    'percentage' => $percentage,
                    'error_summary' => $file->error_summary,
                    'updated_at' => optional($file->updated_at)->toDateTimeString(),
                ];
            })
            ->values();

        $completed = $session->files_processed + $session->files_failed;
        $overall = $session->total_files_uploaded > 0
            ? round(($completed / $session->total_files_uploaded) * 100)
            : 0;

        $fileIds = $session->files()->pluck('id');
        $rejectReasons = collect();
        $failedFiles = collect();
        if ($fileIds->isNotEmpty()) {
            $rejectReasons = DB::table('bank_transactions')
                ->selectRaw("COALESCE(NULLIF(reject_reason, ''), 'Processing error') as reason, COUNT(*) as count")
                ->whereIn('bank_file_id', $fileIds)
                ->where('import_status', 'REJECTED')
                ->groupBy('reason')
                ->orderByDesc('count')
                ->take(20)
                ->get();

            $failedFiles = $session->files()
                ->where('status', 'REJECTED')
                ->select('id', 'original_filename', 'total_rows', 'success_rows', 'rejected_rows', 'error_summary')
                ->latest('id')
                ->get();
        }

        $uploadFailedFiles = collect($session->upload_failed_files ?? [])->map(function ($file) {
            return [
                'id' => null,
                'original_filename' => $file['name'] ?? 'Unknown file',
                'total_rows' => 0,
                'success_rows' => 0,
                'rejected_rows' => 0,
                'error_summary' => $file['error'] ?? 'Upload rejected',
            ];
        });

        if ($uploadFailedFiles->isNotEmpty()) {
            $failedFiles = $failedFiles->concat($uploadFailedFiles)->values();
        }

        return response()->json([
            'ready' => in_array($session->status, ['COMPLETED', 'COMPLETED_WITH_ERRORS'], true),
            'upload_data' => [
                'files_uploaded' => $session->total_files_uploaded,
                'files_failed' => $session->files_failed,
                'bank_type' => $session->bank_type,
                'status' => $session->status,
            ],
            'file_progresses' => $fileProgresses,
            'summary' => [
                'total_files' => $session->total_files_uploaded,
                'files_completed' => $completed,
                'total_rows_processed' => $session->total_rows_processed,
                'total_success' => $session->total_rows_success,
                'total_rejected' => $session->total_rows_rejected,
                'overall_percentage' => $overall,
            ],
            'stats' => $session->toArray(),
            'reject_reasons' => $rejectReasons,
            'failed_files' => $failedFiles,
        ]);
    }

    private function stageForStatus(string $status): string
    {
        return match ($status) {
            'RECEIVED' => 'queued',
            'PROCESSING' => 'processing',
            'PROCESSED', 'PARTIAL' => 'completed',
            'REJECTED' => 'failed_or_rejected',
            default => strtolower($status),
        };
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            'RECEIVED' => 'Waiting for queue worker',
            'PROCESSING' => 'Processing file',
            'PROCESSED' => 'File completed successfully',
            'PARTIAL' => 'File completed with rejected rows',
            'REJECTED' => 'File rejected or all rows rejected',
            default => $status,
        };
    }

    public function downloadRejects(BankFile $bankFile)
    {
        if (!auth()->user()->isAdmin() && $bankFile->created_by !== auth()->id()) {
            abort(403);
        }

        $filename = 'Rejects_' . $bankFile->original_filename . '.csv';

        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $callback = function () use ($bankFile) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'File Name', 'Excel Row', 'Reject Reason', 'Transaction type', 'Amount', 'Debit Account no',
                'IFSC', 'Beneficiary Account No', 'Beneficiary Name', 'Remarks for Client', 'Remarks for Beneficiary',
                'Transaction_id', 'Transaction_Date', 'Invoice_id', 'Invoice_id and Date', 'token_id', 'Email_id',
                'Phone', 'Source File Name', 'File Name (Header)', 'Payment Ref No', 'Status', 'Liquidation Date',
                'Customer Ref No', 'Instrument_No', 'UTR / Bank Remarks', 'Maker ID', 'First Approver', 'Second Approver'
            ]);

            $bankFile->transactions()
                ->where('import_status', 'REJECTED')
                ->chunk(1000, function ($rows) use ($handle, $bankFile) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $bankFile->original_filename,
                            $row->row_number,
                            $row->reject_reason,
                            $row->transaction_type,
                            $row->amount,
                            $row->debit_account_no,
                            $row->ifsc,
                            $row->beneficiary_account_no,
                            $row->beneficiary_name,
                            $row->remarks_for_client,
                            $row->remarks_for_beneficiary,
                            $row->transaction_id,
                            $row->transaction_date ? $row->transaction_date->toDateTimeString() : '',
                            $row->invoice_id,
                            $row->invoice_id_and_date,
                            $row->token_id,
                            $row->email_id,
                            $row->phone,
                            $row->source_file_name,
                            $row->file_name,
                            $row->payment_ref_no,
                            $row->bank_status,
                            $row->liquidation_date ? $row->liquidation_date->toDateTimeString() : '',
                            $row->customer_ref_no,
                            $row->instrument_no,
                            $row->utr_bank_remarks,
                            $row->maker_id,
                            $row->first_approver,
                            $row->second_approver,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadErrorSummary(BankFile $bankFile)
    {
        if (!auth()->user()->isAdmin() && $bankFile->created_by !== auth()->id()) {
            abort(403);
        }

        return response($bankFile->error_summary ?? 'No errors recorded.', 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="error_summary_' . $bankFile->id . '.txt"',
        ]);
    }

    private function validateFileExtensions(Request $request, string $bankType): void
    {
        foreach ($request->file('files', []) as $file) {
            $extension = strtolower($file->getClientOriginalExtension());

            if ($bankType === 'ICICI' && !in_array($extension, ['xlsx', 'xls', 'csv', 'html', 'htm'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'files' => 'For ICICI files, all files must be xlsx, xls, csv, html, or htm. Invalid file: ' . $file->getClientOriginalName(),
                ]);
            }

            if ($bankType === 'SBI' && $extension !== 'txt') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'files' => 'For SBI files, all files must be txt. Invalid file: ' . $file->getClientOriginalName(),
                ]);
            }
        }
    }
}
