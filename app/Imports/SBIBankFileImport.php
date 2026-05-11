<?php

namespace App\Imports;

use App\Models\BankFile;
use App\Models\BankTransaction;
use App\Models\ProcessingError;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SBIBankFileImport
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
    protected const BATCH_SIZE = 1000;
    protected ?string $progressCacheKey = null;

    public function __construct(BankFile $bankFile)
    {
        $this->bankFile = $bankFile;
        $this->progressCacheKey = "bank_file_progress_{$bankFile->id}";
    }

    public function import(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at path: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $lineNumber = 0;

        while (($data = fgetcsv($handle, 0, '~')) !== false) {
            if (empty(array_filter($data))) {
                continue; // Skip completely empty lines
            }

            $lineNumber++;
            $this->fileSummary['total_rows']++;

            // Skip header logic
            if ($lineNumber === 1 && isset($data[0]) && stripos($data[0], 'Sl. No') !== false) {
                $this->fileSummary['total_rows']--;
                continue;
            }

            $this->processRowForBatch($data, $lineNumber);

            // Flush batch when size reached
            if (count($this->transactionsBatch) >= self::BATCH_SIZE) {
                $this->flushBatch();
            }
        }

        fclose($handle);

        // Flush remaining batch
        if (!empty($this->transactionsBatch) || !empty($this->errorsBatch)) {
            $this->flushBatch();
        }

        // Update bank file summary
        $this->updateFileSummary();
        $this->updateProgress();
    }

    protected function processRowForBatch(array $data, int $lineNumber): void
    {
        try {
            if (count($data) < 19) {
                $this->errorsBatch[] = [
                    'bank_file_id' => $this->bankFile->id,
                    'row_number' => $lineNumber,
                    'error_code' => 'INSUFFICIENT_COLUMNS',
                    'error_message' => 'Row has insufficient columns (' . count($data) . ')',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $this->fileSummary['rejected_rows']++;
                return;
            }

            $amount = floatval(preg_replace('/[^0-9.]/', '', $data[4] ?? 0));

            // Validation
            $rejectReason = null;
            if ($amount <= 0) {
                $rejectReason = 'Amount is empty or <= 0';
            } elseif (empty(trim($data[5] ?? ''))) {
                $rejectReason = 'Debit Account No missing';
            } elseif (empty(trim($data[12] ?? ''))) {
                $rejectReason = 'Transaction Date missing';
            }

            $transactionDate = null;
            if (!empty($data[12])) {
                try {
                    $transactionDate = Carbon::createFromFormat('d/m/Y', trim($data[12]));
                } catch (\Exception $e) {
                    try {
                        $transactionDate = Carbon::parse($data[12]);
                    } catch (\Exception $e2) {
                        if (!$rejectReason) {
                            $rejectReason = 'Invalid transaction date format';
                        }
                    }
                }
            }

            $liquidationDate = null;
            if (!empty($data[17])) {
                try {
                    $liquidationDate = Carbon::createFromFormat('d/m/Y', trim($data[17]));
                } catch (\Exception $e) {
                    try {
                        $liquidationDate = Carbon::parse($data[17]);
                    } catch (\Exception $e2) {
                        // Optional field, don't reject
                    }
                }
            }

            $payload = [
                'Sl. No' => $data[0] ?? '',
                'CORP ID' => $data[1] ?? '',
                'Customer Name' => $data[2] ?? '',
                'Transaction type' => $data[3] ?? '',
                'Amount' => $data[4] ?? '',
                'Debit Account no' => $data[5] ?? '',
                'IFSC' => $data[6] ?? '',
                'Beneficiary Account No' => $data[7] ?? '',
                'Beneficiary Name' => $data[8] ?? '',
                'Remarks for Client' => $data[9] ?? '',
                'Remarks for Beneficiary' => $data[10] ?? '',
                'Transaction Id' => $data[11] ?? '',
                'TRANSACTION DATE' => $data[12] ?? '',
                'Invoice Id' => $data[13] ?? '',
                'Invoice Id & Advice Date' => $data[14] ?? '',
                'Email Id' => $data[15] ?? '',
                'Mobile' => $data[16] ?? '',
                'PAYMENT DATE' => $data[17] ?? '',
                'PAYMENT STATUS' => $data[18] ?? '',
                'PAYMENT UTR NUMBER' => $data[19] ?? ''
            ];

            if ($rejectReason) {
                $this->fileSummary['rejected_rows']++;
                $this->errorsBatch[] = [
                    'bank_file_id' => $this->bankFile->id,
                    'row_number' => $lineNumber,
                    'error_code' => 'VALIDATION_ERROR',
                    'error_message' => $rejectReason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                return;
            }

            $this->fileSummary['success_rows']++;
            $this->fileSummary['total_amount'] += $amount;

            $this->transactionsBatch[] = [
                'bank_file_id' => $this->bankFile->id,
                'row_number' => $lineNumber,
                'import_status' => 'OK',
                'reject_reason' => null,
                'transaction_type' => trim($data[3] ?? ''),
                'amount' => $amount,
                'debit_account_no' => trim($data[5] ?? ''),
                'ifsc' => trim($data[6] ?? ''),
                'beneficiary_account_no' => trim($data[7] ?? ''),
                'beneficiary_name' => trim($data[8] ?? ''),
                'remarks_for_client' => trim($data[9] ?? ''),
                'remarks_for_beneficiary' => trim($data[10] ?? ''),
                'transaction_id' => trim($data[11] ?? ''),
                'transaction_date' => $transactionDate,
                'invoice_id' => trim($data[13] ?? ''),
                'invoice_id_and_date' => trim($data[14] ?? ''),
                'email_id' => trim($data[15] ?? ''),
                'phone' => trim($data[16] ?? ''),
                'liquidation_date' => $liquidationDate,
                'bank_status' => trim($data[18] ?? ''),
                'payment_ref_no' => trim($data[19] ?? ''),
                'maker_id' => trim($data[1] ?? ''),
                'payload_json' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } catch (\Exception $e) {
            $this->fileSummary['rejected_rows']++;
            $this->errorsBatch[] = [
                'bank_file_id' => $this->bankFile->id,
                'row_number' => $lineNumber,
                'error_code' => 'PARSE_ERROR',
                'error_message' => 'Error parsing row: ' . $e->getMessage(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    protected function flushBatch(): void
    {
        if (empty($this->transactionsBatch) && empty($this->errorsBatch)) {
            return;
        }

        try {
            DB::transaction(function () {
                // Batch insert transactions
                if (!empty($this->transactionsBatch)) {
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
            Log::error('Batch insert failed for SBI file ' . $this->bankFile->id . ': ' . $e->getMessage());
        }

        // Update progress
        $this->updateProgress();

        // Clear batches
        $this->transactionsBatch = [];
        $this->errorsBatch = [];
    }

    protected function updateFileSummary(): void
    {
        if ($this->fileSummary['total_rows'] > 0) {
            try {
                $this->bankFile->update([
                    'success_rows' => $this->fileSummary['success_rows'],
                    'rejected_rows' => $this->fileSummary['rejected_rows'],
                    'total_rows' => $this->fileSummary['total_rows'],
                    'total_amount' => $this->fileSummary['total_amount'],
                    'status' => $this->fileSummary['success_rows'] > 0 ? 'PROCESSED' : 'REJECTED',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update SBI bank file summary: ' . $e->getMessage());
            }
        }
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
                    'bank_type' => 'SBI',
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
            Log::error('Failed to update SBI progress cache: ' . $e->getMessage());
        }
    }
}
