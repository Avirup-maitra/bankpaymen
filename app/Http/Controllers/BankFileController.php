<?php

namespace App\Http\Controllers;

use App\Models\BankFile;
use App\Models\BankTransaction;
use App\Services\BankFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        return view('bank-files.index', compact('files'));
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

    public function store(Request $request, BankFileService $service)
    {
        $request->validate([
            'bank_type' => 'required|in:ICICI,SBI',
            'files' => [
                'required',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value)) {
                        $fail('Please select at least one file.');
                        return;
                    }

                    $bankType = $request->input('bank_type');

                    foreach ($value as $file) {
                        $extension = strtolower($file->getClientOriginalExtension());

                        if ($bankType === 'ICICI') {
                            if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
                                $fail('For ICICI files, all files must be of type: xlsx, xls, or csv. File "' . $file->getClientOriginalName() . '" has type: ' . $extension);
                                return;
                            }
                        } elseif ($bankType === 'SBI') {
                            if ($extension !== 'txt') {
                                $fail('For SBI files, all files must be of type: txt. File "' . $file->getClientOriginalName() . '" has type: ' . $extension);
                                return;
                            }
                        }
                    }
                },
            ],
            'files.*' => 'file|max:153600', // Max 150MB per file
        ]);

        try {
            // Generate unique session ID for this bulk upload
            $sessionId = 'bulk_' . auth()->id() . '_' . time() . '_' . uniqid();
            
            $uploadedCount = 0;
            $failedCount = 0;
            $successFiles = [];
            $failedFiles = [];
            $fileIds = [];
            $totalFiles = count($request->file('files'));

            foreach ($request->file('files') as $uploadedFile) {
                try {
                    $bankFile = $service->handleUpload(
                        $uploadedFile,
                        $request->user(),
                        'MANUAL',
                        $request->input('bank_type')
                    );

                    $uploadedCount++;
                    $successFiles[] = $uploadedFile->getClientOriginalName();
                    $fileIds[] = $bankFile->id;
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedFiles[] = [
                        'name' => $uploadedFile->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Dispatch batch processing jobs (process multiple files per job for efficiency)
            // For 4,700 files, we'll batch them into groups of 50 files per job
            $batchSize = 50;
            $batches = array_chunk($fileIds, $batchSize);
            
            foreach ($batches as $batch) {
                \App\Jobs\ProcessBulkFiles::dispatch($batch);
            }

            // Cache upload session data
            \Illuminate\Support\Facades\Cache::put(
                "bulk_upload_{$sessionId}",
                [
                    'files_to_process' => $uploadedCount,
                    'files_uploaded' => $uploadedCount,
                    'files_failed' => $failedCount,
                    'total_files' => $totalFiles,
                    'failed_files' => $failedFiles,
                    'bank_type' => $request->input('bank_type'),
                    'file_ids' => $fileIds,
                    'batch_size' => $batchSize,
                    'total_batches' => count($batches),
                    'timestamp' => now(),
                ],
                now()->addHours(24)
            );

            // Dispatch stats collection job (will run after all jobs are queued)
            \App\Jobs\CollectBulkUploadStats::dispatch(auth()->id(), $sessionId)->delay(now()->addSeconds(60));

            // Return response with session ID for polling
            if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'uploaded_count' => $uploadedCount,
                    'failed_count' => $failedCount,
                    'total_files' => $totalFiles,
                    'failed_files' => $failedFiles,
                    'message' => "Uploaded {$uploadedCount} file(s). Processing started...",
                ], 200);
            }

            // Redirect with session ID for server-rendered pages
            return redirect()->route('bank-files.summary', ['session_id' => $sessionId])
                ->with('success', "Uploaded {$uploadedCount} file(s). Processing started...")
                ->with('failed_count', $failedCount)
                ->with('total_files', $totalFiles);

        } catch (\Exception $e) {
            return back()->withErrors(['files' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Display bulk upload summary
     * GET /bank-files/summary?session_id=SESSION_ID
     */
    public function summary(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        if (!$sessionId) {
            return redirect()->route('bank-files.index')->with('error', 'Invalid session');
        }

        // Get upload session data
        $uploadData = \Illuminate\Support\Facades\Cache::get("bulk_upload_{$sessionId}", []);
        
        // Get stats (may not be ready yet)
        $stats = \Illuminate\Support\Facades\Cache::get("bulk_upload_stats_{$sessionId}", null);

        return view('bank-files.summary', [
            'sessionId' => $sessionId,
            'uploadData' => $uploadData,
            'stats' => $stats,
        ]);
    }

    /**
     * Get bulk upload statistics (API endpoint)
     * GET /api/bank-files/bulk-stats?session_id=SESSION_ID
     */
    public function bulkUploadStats(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        if (!$sessionId) {
            return response()->json(['error' => 'Session ID required'], 400);
        }

        // Get upload session data
        $uploadData = \Illuminate\Support\Facades\Cache::get("bulk_upload_{$sessionId}", []);
        
        if (empty($uploadData)) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        // Collect real-time progress from individual files
        $fileProgresses = [];
        $totalProcessed = 0;
        $totalSuccess = 0;
        $totalRejected = 0;
        
        if (!empty($uploadData['file_ids'])) {
            foreach ($uploadData['file_ids'] as $fileId) {
                $progressKey = "bank_file_progress_{$fileId}";
                $fileProgress = \Illuminate\Support\Facades\Cache::get($progressKey);
                
                if ($fileProgress) {
                    $fileProgresses[] = $fileProgress;
                    $totalProcessed += $fileProgress['total_rows'];
                    $totalSuccess += $fileProgress['success_rows'];
                    $totalRejected += $fileProgress['rejected_rows'];
                }
            }
        }

        // Check if processing is complete
        $stats = \Illuminate\Support\Facades\Cache::get("bulk_upload_stats_{$sessionId}");
        $isReady = !empty($stats);

        return response()->json([
            'ready' => $isReady,
            'upload_data' => $uploadData,
            'file_progresses' => $fileProgresses,
            'summary' => [
                'total_files' => count($fileProgresses),
                'total_rows_processed' => $totalProcessed,
                'total_success' => $totalSuccess,
                'total_rejected' => $totalRejected,
                'overall_percentage' => $uploadData['files_uploaded'] > 0 ? 
                    round((count($fileProgresses) / $uploadData['files_uploaded']) * 100) : 0,
            ],
            'stats' => $stats,
            'message' => $isReady ? 'Processing complete!' : 'Still processing files...',
        ]);
    }

    public function downloadRejects(BankFile $bankFile)
    {
        if (!auth()->user()->isAdmin() && $bankFile->created_by !== auth()->id()) {
            abort(403);
        }

        $filename = 'Rejects_' . $bankFile->original_filename . '.csv';

        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires'             => '0',
            'Pragma'              => 'public',
        ];

        $callback = function () use ($bankFile) {
            $handle = fopen('php://output', 'w');

            // Header: File Name, Excel Row, Reject Reason, + 26 columns
            // We get 26 columns from payload_json of first rejected item or just static list.
            // Requirement 1 lists 26 headers.
            
            $staticHeaders = [
                'File Name', 'Excel Row', 'Reject Reason',
                'Transaction type', 'Amount', 'Debit Account no', 'IFSC', 'Beneficiary Account No', 
                'Beneficiary Name', 'Remarks for Client', 'Remarks for Beneficiary', 'Transaction_id', 
                'Transaction_Date', 'Invoice_id', 'Invoice_id and Date', 'token_id', 'Email_id', 
                'Phone', 'Source File Name', 'File Name (Header)', 'Payment Ref No', 'Status', 
                'Liquidation Date', 'Customer Ref No', 'Instrument_No', 'UTR / Bank Remarks', 
                'Maker ID', 'First Approver', 'Second Approver'
            ];
            
            fputcsv($handle, $staticHeaders);

            $bankFile->transactions()
                ->where('import_status', 'REJECTED')
                ->chunk(1000, function ($rows) use ($handle, $bankFile) {
                    foreach ($rows as $row) {
                        $payload = $row->payload_json ?? [];
                        // Map payload to headers. 
                        // Payload keys are slugified. We need to know which slug corresponds to which header.
                        // Or just dump payload values if order is preserved? 
                        // Order in JSON is not guaranteed. 
                        // Better to map by key.
                        
                        $data = [
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
                            $row->second_approver
                        ];
                        
                        fputcsv($handle, $data);
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

         // Simple text download of error_summary
         return response($bankFile->error_summary ?? 'No errors recorded.', 200, [
             'Content-Type' => 'text/plain',
             'Content-Disposition' => 'attachment; filename="error_summary_' . $bankFile->id . '.txt"',
         ]);
    }
}
