<?php

namespace App\Imports;

use App\Models\BankFile;
use App\Models\BankTransaction;
use App\Models\ProcessingError;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BankFileImport implements ToCollection, WithHeadingRow, WithStartRow, WithChunkReading, SkipsEmptyRows
{
    protected BankFile $bankFile;
    protected array $transactionsBatch = [];
    protected array $errorsBatch = [];
    protected array $fileSummary = [
        'success_rows' => 0,
        'rejected_rows' => 0,
        'total_rows' => 0,
        'total_amount' => 0,
    ];
    protected int $currentRow = 4;
    protected bool $reachedEnd = false;
    protected const BATCH_SIZE = 1000;
    protected ?string $progressCacheKey = null;

    public function __construct(BankFile $bankFile)
    {
        $this->bankFile = $bankFile;
        $this->progressCacheKey = "bank_file_progress_{$bankFile->id}";
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function startRow(): int
    {
        return 4;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if ($this->reachedEnd) {
                continue;
            }

            $this->currentRow++;
            $actualRow = $this->currentRow;

            // Check for Summary Row
            $txnType = $row['transaction_type'] ?? '';
            if (Str::startsWith(strtoupper($txnType), 'SUM AMOU') || Str::contains(strtoupper($txnType), 'END OF REPORT')) {
                $this->reachedEnd = true;
                continue;
            }

            $this->processRowForBatch($row, $actualRow);

            // Flush batch when size reached or end of chunk
            if (count($this->transactionsBatch) >= self::BATCH_SIZE) {
                $this->flushBatch();
            }
        }

        // Flush remaining batch after chunk processing
        if (!empty($this->transactionsBatch) || !empty($this->errorsBatch)) {
            $this->flushBatch();
        }
    }

    protected function processRowForBatch(Collection $row, int $actualRow): void
    {
        $amount = $this->cleanAmount($row['amount'] ?? null);
        
        // Clean status (handle NBSP and extra spaces)
        $statusRaw = $row['status'] ?? '';
        $statusRaw = str_replace(["\xC2\xA0", "\xA0"], ' ', $statusRaw);
        $status = trim(preg_replace('/\s+/u', ' ', $statusRaw));
        
        // Normalize Status
        $status = Str::title(strtolower($status));

        $paymentRef = trim($row['payment_ref_no'] ?? '');
        $txnDate = $this->parseDate($row['transaction_date'] ?? null);
        
        $rejectReason = null;
        $importStatus = 'OK';

        // Validation Rules
        if ($amount <= 0) {
            $rejectReason = 'Amount is empty or <= 0';
        } elseif (empty($paymentRef)) {
            $rejectReason = 'Payment Ref No missing';
        } elseif (!$txnDate) {
            $rejectReason = 'Transaction_Date missing/unparseable';
        } elseif (strcasecmp($status, 'Paid') !== 0) {
            $rejectReason = "Bank status not Paid (Current: $status)";
        }

        if ($rejectReason) {
            $importStatus = 'REJECTED';
            $this->fileSummary['rejected_rows']++;
        } else {
            $this->fileSummary['success_rows']++;
            $this->fileSummary['total_amount'] += $amount;
        }
        
        $this->fileSummary['total_rows']++;

        // Add to batch
        $this->transactionsBatch[] = [
            'bank_file_id' => $this->bankFile->id,
            'row_number' => $actualRow,
            'import_status' => $importStatus,
            'reject_reason' => $rejectReason,
            'transaction_type' => $row['transaction_type'] ?? null,
            'amount' => $amount,
            'debit_account_no' => $row['debit_account_no'] ?? null,
            'ifsc' => $row['ifsc'] ?? null,
            'beneficiary_account_no' => $row['beneficiary_account_no'] ?? null,
            'beneficiary_name' => $row['beneficiary_name'] ?? null,
            'remarks_for_client' => $row['remarks_for_client'] ?? null,
            'remarks_for_beneficiary' => $row['remarks_for_beneficiary'] ?? null,
            'transaction_id' => $row['transaction_id'] ?? null,
            'transaction_date' => $txnDate,
            'invoice_id' => $row['invoice_id'] ?? null,
            'invoice_id_and_date' => $row['invoice_id_and_date'] ?? null,
            'token_id' => $row['token_id'] ?? null,
            'email_id' => $row['email_id'] ?? null,
            'phone' => $row['phone'] ?? null,
            'source_file_name' => $row['source_file_name'] ?? null,
            'file_name' => $row['file_name'] ?? null,
            'payment_ref_no' => $row['payment_ref_no'] ?? null,
            'bank_status' => $row['status'] ?? null,
            'liquidation_date' => $this->parseDate($row['liquidation_date'] ?? null),
            'customer_ref_no' => $row['customer_ref_no'] ?? null,
            'instrument_no' => $row['instrument_no'] ?? null,
            'utr_bank_remarks' => $row['utr_bank_remarks'] ?? null,
            'maker_id' => $row['maker_id'] ?? null,
            'first_approver' => $row['first_approver'] ?? null,
            'second_approver' => $row['second_approver'] ?? null,
            'payload_json' => json_encode($row->toArray()),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // If rejected, add to errors batch
        if ($rejectReason) {
            $this->errorsBatch[] = [
                'bank_file_id' => $this->bankFile->id,
                'row_number' => $actualRow,
                'error_code' => 'VALIDATION_ERROR',
                'error_message' => $rejectReason,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    protected function flushBatch(): void
    {
        if (empty($this->transactionsBatch)) {
            return;
        }

        try {
            DB::transaction(function () {
                // Batch insert transactions
                if (!empty($this->transactionsBatch)) {
                    // Insert in smaller chunks to avoid packet size issues
                    $chunks = array_chunk($this->transactionsBatch, 500);
                    foreach ($chunks as $chunk) {
                        BankTransaction::insert($chunk);
                    }
                }

                // Batch insert errors
                if (!empty($this->errorsBatch)) {
                    $chunks = array_chunk($this->errorsBatch, 500);
                    foreach ($chunks as $chunk) {
                        ProcessingError::insert($chunk);
                    }
                }
            });
        } catch (\Exception $e) {
            \Log::error('Batch insert failed for bank file ' . $this->bankFile->id . ': ' . $e->getMessage());
            // Continue processing other batches
        }

        // Update progress in cache
        $this->updateProgress();

        // Clear batches
        $this->transactionsBatch = [];
        $this->errorsBatch = [];
    }

    protected function updateProgress(): void
    {
        if (!$this->progressCacheKey) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Cache::put(
                $this->progressCacheKey,
                [
                    'bank_file_id' => $this->bankFile->id,
                    'filename' => $this->bankFile->original_filename,
                    'bank_type' => $this->bankFile->source_type,
                    'total_rows' => $this->fileSummary['total_rows'],
                    'success_rows' => $this->fileSummary['success_rows'],
                    'rejected_rows' => $this->fileSummary['rejected_rows'],
                    'total_amount' => $this->fileSummary['total_amount'],
                    'percentage' => $this->fileSummary['total_rows'] > 0 ? 
                        round(($this->fileSummary['success_rows'] / $this->fileSummary['total_rows']) * 100) : 0,
                    'timestamp' => now(),
                ],
                now()->addHours(24)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to update progress cache: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        // Final flush on object destruction
        $this->flushBatch();
        
        // Update bank file summary once at the end
        if ($this->fileSummary['total_rows'] > 0) {
            try {
                $this->bankFile->update([
                    'success_rows' => $this->fileSummary['success_rows'],
                    'rejected_rows' => $this->fileSummary['rejected_rows'],
                    'total_rows' => $this->fileSummary['total_rows'],
                    'total_amount' => $this->fileSummary['total_amount'],
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to update bank file summary: ' . $e->getMessage());
            }
        }
    }

    private function cleanAmount($val)
    {
        if (is_null($val)) return 0;
        // Remove commas, spaces
        $val = str_replace([',', ' '], '', $val);
        return (float) $val;
    }

    private function parseDate($val)
    {
        if (!$val) return null;
        try {
            // Excel dates are sometimes numbers, sometimes strings.
            if (is_numeric($val)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
            }
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }
}
