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
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SimpleBankFileImport implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows, WithCustomCsvSettings
{
    protected BankFile $bankFile;
    protected string $delimiter;

    public function __construct(BankFile $bankFile, string $delimiter = ',')
    {
        $this->bankFile = $bankFile;
        $this->delimiter = $delimiter;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
            'input_encoding' => 'ISO-8859-1', // Robust encoding
        ];
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function startRow(): int
    {
        return 4;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // $row matches the header names (slugified by default by Laravel Excel)
            // We need to map them to our DB columns.
            // Raw headers are: Transaction type, Amount, Debit Account no, ...
            // Laravel Excel usually converts "Transaction type" to "transaction_type".

            $rowIndex = $row->get('row_number') ?? null; // If we attached row numbers, but we don't automatedly get exact excel row number easily in chunks without some tracking. 
            // Actually, `WithRowNumber` trait might help but complicates chunks.
            // We can estimate or just rely on data. 
            // Let's increment a counter if needed, but for now we just process.
            // Wait, we need accurate row numbers for reporting.
            // But doing it with chunks is tricky. 
            // We can resolve this by not using `WithChunkReading` if files are small, but requirement says "Chunk reading for performance".
            // A common trick is to use a static counter or pass an offset. 
            // Or just store the data and let the DB ID be the reference, but user wants "excel row number".
            // Since we are `ToCollection`, we validly get a chunk of rows. 
            // We can't easily get the absolute row number from the file without manual tracking.
            // Let's add a property to track rows processed. 
            // But `BankFileImport` is instantiated once. 
            // So we can maintain state.

             $this->processRow($row);
        }
    }
    
    // We need to track current row number.
    // Start row is 4.
    protected int $currentRow = 4;

    protected function processRow(Collection $row)
    {
        $this->currentRow++;
        $currentRowNumber = $this->currentRow - 1; 
        $actualRow = $this->currentRow;
        $this->currentRow++;

        // Check for Summary Row
        $txnType = $row['transaction_type'] ?? '';
        if (Str::startsWith(strtoupper($txnType), 'SUM AMOUNT')) {
            return; // Skip this row
        }

        $amount = $this->cleanAmount($row['amount'] ?? null);
        
        // Clean status (handle NBSP and extra spaces)
        $statusRaw = $row['status'] ?? '';
        // Replace NBSP with space
        $statusRaw = str_replace(["\xC2\xA0", "\xA0"], ' ', $statusRaw);
        $status = trim(preg_replace('/\s+/u', ' ', $statusRaw));
        
        // Normalize Status
        $status = Str::title(strtolower($status)); // Converts "PAID", "paid" -> "Paid"

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
        }

        try {
            DB::transaction(function () use ($row, $amount, $txnDate, $importStatus, $rejectReason, $actualRow) {
                $item = BankTransaction::create([
                    'bank_file_id' => $this->bankFile->id,
                    'row_number' => $actualRow,
                    'import_status' => $importStatus,
                    'reject_reason' => $rejectReason,
                    
                    // Normalized
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
                    
                    'payload_json' => $row->toArray(), // Store full row data
                ]);

                if ($importStatus === 'REJECTED') {
                    ProcessingError::create([
                        'bank_file_id' => $this->bankFile->id,
                        'bank_transaction_id' => $item->id,
                        'row_number' => $actualRow,
                        'error_code' => 'VALIDATION_ERROR',
                        'error_message' => $rejectReason,
                    ]);
                    
                    $this->bankFile->increment('rejected_rows');
                } else {
                    $this->bankFile->increment('success_rows');
                    // We should probably just sum at the end or update incrementally.
                    // incrementing total_amount only for OK rows? 
                    // "Files received today = count... Total amount processed today = SUM(amount)"
                    // It implies total_amount in bank_files might be total of file or total processed.
                    // The requirement says "Update bank_files summary counts + total_amount."
                    // Let's assume total_amount is sum of valid transactions.
                    $this->bankFile->increment('total_amount', $amount);
                }
                $this->bankFile->increment('total_rows');
            });
        } catch (\Exception $e) {
             ProcessingError::create([
                'bank_file_id' => $this->bankFile->id,
                'row_number' => $actualRow,
                'error_code' => 'DB_INSERT_ERROR',
                'error_message' => $e->getMessage(),
            ]);
            $this->bankFile->increment('rejected_rows');
            // Check if total_rows needs incrementing if it failed completely? 
            // Yes, it was a row in the file.
            $this->bankFile->increment('total_rows');
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
            // Maatwebsite handles this if configured, but let's be robust.
            if (is_numeric($val)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
            }
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }
}
