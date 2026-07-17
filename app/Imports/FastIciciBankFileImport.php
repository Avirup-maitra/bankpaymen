<?php

namespace App\Imports;

use App\Models\BankFile;
use App\Models\BankTransaction;
use App\Models\ProcessingError;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FastIciciBankFileImport
{
    private const BATCH_SIZE = 1000;

    private BankFile $bankFile;
    private array $transactionsBatch = [];
    private array $errorsBatch = [];
    private array $summary = [
        'success_rows' => 0,
        'rejected_rows' => 0,
        'total_rows' => 0,
        'total_amount' => 0,
    ];

    public function __construct(BankFile $bankFile)
    {
        $this->bankFile = $bankFile;
    }

    public function import(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            throw new \Exception("File not found at path: {$filePath}");
        }

        $sample = file_get_contents($filePath, false, null, 0, 4096) ?: '';

        if ($this->isZipSpreadsheet($sample) || $this->isOleSpreadsheet($sample)) {
            return false;
        }

        $this->stage('reading', 'Reading file with fast ICICI reader', 5);

        if (stripos($sample, '<table') !== false || stripos($sample, '<html') !== false) {
            $this->importHtml($filePath);
        } else {
            $this->importDelimited($filePath, $this->detectDelimiter($sample));
        }

        $this->flushBatch();
        $this->finalize();

        return true;
    }

    private function importHtml(string $filePath): void
    {
        $this->stage('parsing_html', 'Parsing HTML type Excel table', 15);

        $html = file_get_contents($filePath);
        if ($html === false || trim($html) === '') {
            throw new \Exception('File is empty or unreadable.');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $rows = $dom->getElementsByTagName('tr');
        $headers = null;
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;
            $values = [];

            foreach ($row->childNodes as $cell) {
                if (! in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    continue;
                }

                $values[] = $this->cleanCell($cell->textContent ?? '');
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            if ($headers === null) {
                $candidate = $this->headersFromRow($values);
                if ($this->hasRequiredHeaders($candidate)) {
                    $headers = $candidate;
                    $this->stage('validating', 'Header detected. Validating and inserting rows.', 25);
                }
                continue;
            }

            $this->processMappedRow($this->mapRow($headers, $values), $rowNumber);
        }

        if ($headers === null) {
            throw new \Exception('Could not detect ICICI header row in HTML Excel file.');
        }
    }

    private function importDelimited(string $filePath, string $delimiter): void
    {
        $this->stage('parsing_text', 'Parsing delimited ICICI file', 15);

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Could not open delimited file.');
        }

        $headers = null;
        $rowNumber = 0;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            $values = array_map(fn ($value) => $this->cleanCell((string) $value), $values);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            if ($headers === null) {
                $candidate = $this->headersFromRow($values);
                if ($this->hasRequiredHeaders($candidate)) {
                    $headers = $candidate;
                    $this->stage('validating', 'Header detected. Validating and inserting rows.', 25);
                }
                continue;
            }

            $this->processMappedRow($this->mapRow($headers, $values), $rowNumber);
        }

        fclose($handle);

        if ($headers === null) {
            throw new \Exception('Could not detect ICICI header row in delimited file.');
        }
    }

    private function processMappedRow(array $row, int $rowNumber): void
    {
        if (isset($row['data_string'])) {
            $row = $this->expandPackedIciciRow($row);
        }

        $txnType = (string) ($row['transaction_type'] ?? '');
        if (Str::startsWith(strtoupper($txnType), 'SUM AMOU') || Str::contains(strtoupper($txnType), 'END OF REPORT')) {
            return;
        }

        $amount = $this->cleanAmount($row['amount'] ?? null);
        $statusRaw = str_replace(["Â ", " "], ' ', (string) ($row['status'] ?? ''));
        $status = Str::title(strtolower(trim(preg_replace('/\s+/u', ' ', $statusRaw))));
        $paymentRef = trim((string) ($row['payment_ref_no'] ?? ''));
        $txnDate = $this->parseDate($row['transaction_date'] ?? null);

        $rejectReason = null;
        $importStatus = 'OK';

        if ($amount <= 0) {
            $rejectReason = 'Amount is empty or <= 0';
        } elseif ($paymentRef === '') {
            $rejectReason = 'Payment Ref No missing';
        } elseif (! $txnDate) {
            $rejectReason = 'Transaction_Date missing/unparseable';
        } elseif (strcasecmp($status, 'Paid') !== 0) {
            $bankReason = trim((string) ($row['rejection_reason'] ?? ''));
            $rejectReason = "Bank status not Paid (Current: $status)" . ($bankReason !== '' ? ". Bank reason: {$bankReason}" : '');
        }

        if ($rejectReason) {
            $importStatus = 'REJECTED';
            $this->summary['rejected_rows']++;
        } else {
            $this->summary['success_rows']++;
            $this->summary['total_amount'] += $amount;
        }

        $this->summary['total_rows']++;

        $this->transactionsBatch[] = [
            'bank_file_id' => $this->bankFile->id,
            'row_number' => $rowNumber,
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
            'payload_json' => json_encode($row),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($rejectReason) {
            $this->errorsBatch[] = [
                'bank_file_id' => $this->bankFile->id,
                'row_number' => $rowNumber,
                'error_code' => 'VALIDATION_ERROR',
                'error_message' => $rejectReason,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($this->transactionsBatch) >= self::BATCH_SIZE) {
            $this->flushBatch();
        }
    }

    private function flushBatch(): void
    {
        if (empty($this->transactionsBatch) && empty($this->errorsBatch)) {
            return;
        }

        $this->stage('inserting', 'Batch inserting validated rows', 55);

        DB::transaction(function () {
            foreach (array_chunk($this->transactionsBatch, 500) as $chunk) {
                BankTransaction::insert($chunk);
            }

            foreach (array_chunk($this->errorsBatch, 500) as $chunk) {
                ProcessingError::insert($chunk);
            }
        });

        $this->transactionsBatch = [];
        $this->errorsBatch = [];
        $this->progress();
    }

    private function finalize(): void
    {
        $this->stage('finalizing', 'Finalizing file summary', 90);

        $this->bankFile->update([
            'success_rows' => $this->summary['success_rows'],
            'rejected_rows' => $this->summary['rejected_rows'],
            'total_rows' => $this->summary['total_rows'],
            'total_amount' => $this->summary['total_amount'],
        ]);

        $this->stage('completed', 'File processing completed', 100);
    }

    private function headersFromRow(array $values): array
    {
        $headers = [];
        foreach ($values as $index => $value) {
            $key = $this->normalizeHeader($value);
            if ($key !== '') {
                $headers[$index] = $key;
            }
        }

        return $headers;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        $values = array_values($headers);

        $standardReport = in_array('amount', $values, true)
            && in_array('payment_ref_no', $values, true)
            && in_array('status', $values, true);

        $packedResponseReport = in_array('data_string', $values, true)
            && in_array('status', $values, true);

        return $standardReport || $packedResponseReport;
    }

    private function mapRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $key) {
            $row[$key] = $values[$index] ?? null;
        }

        return $row;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower($this->cleanCell($header));
        $header = str_replace(['&', '/', '-'], ' ', $header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        $header = trim($header, '_');

        return match ($header) {
            'transaction_type' => 'transaction_type',
            'amount' => 'amount',
            'debit_account_no', 'debit_account_number' => 'debit_account_no',
            'ifsc' => 'ifsc',
            'beneficiary_account_no', 'beneficiary_account_number' => 'beneficiary_account_no',
            'beneficiary_name' => 'beneficiary_name',
            'remarks_for_client' => 'remarks_for_client',
            'remarks_for_beneficiary' => 'remarks_for_beneficiary',
            'transaction_id' => 'transaction_id',
            'transaction_date' => 'transaction_date',
            'invoice_id' => 'invoice_id',
            'invoice_id_and_date', 'invoice_id_advice_date' => 'invoice_id_and_date',
            'token_id' => 'token_id',
            'email_id' => 'email_id',
            'phone' => 'phone',
            'source_file_name' => 'source_file_name',
            'file_name' => 'file_name',
            'data_string' => 'data_string',
            'payment_ref_no', 'payment_ref_number' => 'payment_ref_no',
            'status' => 'status',
            'rejection_reason', 'reject_reason' => 'rejection_reason',
            'liquidation_date' => 'liquidation_date',
            'customer_ref_no', 'customer_ref_number' => 'customer_ref_no',
            'instrument_no', 'instrument_number' => 'instrument_no',
            'utr_bank_remarks' => 'utr_bank_remarks',
            'maker_id' => 'maker_id',
            'first_approver' => 'first_approver',
            'second_approver' => 'second_approver',
            default => $header,
        };
    }

    private function cleanCell(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["Â ", " ", "\r"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value ?? '');
    }

    private function expandPackedIciciRow(array $row): array
    {
        $parts = array_map(fn ($value) => $this->cleanCell((string) $value), explode('|', (string) ($row['data_string'] ?? '')));
        $status = $this->normalizePackedStatus($row['status'] ?? null);
        $paymentRef = $parts[11] ?? ($parts[10] ?? null);

        return array_merge($row, [
            'transaction_type' => $parts[0] ?? null,
            'amount' => $parts[1] ?? null,
            'debit_account_no' => $parts[2] ?? null,
            'ifsc' => $parts[3] ?? null,
            'beneficiary_account_no' => $parts[4] ?? null,
            'beneficiary_name' => $parts[5] ?? null,
            'remarks_for_client' => $parts[6] ?? null,
            'remarks_for_beneficiary' => $parts[7] ?? null,
            'transaction_id' => $parts[10] ?? null,
            'transaction_date' => $parts[9] ?? null,
            'invoice_id' => $parts[10] ?? null,
            'invoice_id_and_date' => $parts[11] ?? null,
            'source_file_name' => $row['file_name'] ?? null,
            'file_name' => $parts[18] ?? ($row['file_name'] ?? null),
            'payment_ref_no' => $paymentRef,
            'status' => $status,
            'customer_ref_no' => $parts[10] ?? null,
            'instrument_no' => $parts[11] ?? null,
            'utr_bank_remarks' => $row['rejection_reason'] ?? null,
        ]);
    }

    private function normalizePackedStatus($value): string
    {
        $status = strtoupper(trim((string) $value));

        return match ($status) {
            'P' => 'Paid',
            'R' => 'Rejected',
            default => $this->cleanCell((string) $value),
        };
    }

    private function cleanAmount($value): float
    {
        if ($value === null) {
            return 0;
        }

        return (float) str_replace([',', ' '], '', preg_replace('/[^0-9.\-]/', '', (string) $value));
    }

    private function parseDate($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            }

            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isEmptyRow(array $values): bool
    {
        return count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0;
    }

    private function detectDelimiter(string $sample): string
    {
        return substr_count($sample, "	") > substr_count($sample, ',') ? "	" : ',';
    }

    private function isZipSpreadsheet(string $sample): bool
    {
        return str_starts_with($sample, "PK");
    }

    private function isOleSpreadsheet(string $sample): bool
    {
        return str_starts_with($sample, "ÐÏà");
    }

    private function progress(): void
    {
        $this->stage('processing_rows', 'Rows are being validated and inserted', 65);
    }

    private function stage(string $stage, string $message, int $percentage): void
    {
        Cache::put("bank_file_progress_{$this->bankFile->id}", [
            'bank_file_id' => $this->bankFile->id,
            'filename' => $this->bankFile->original_filename,
            'bank_type' => $this->bankFile->bank_type,
            'status' => $this->bankFile->status,
            'stage' => $stage,
            'stage_message' => $message,
            'total_rows' => $this->summary['total_rows'],
            'success_rows' => $this->summary['success_rows'],
            'rejected_rows' => $this->summary['rejected_rows'],
            'total_amount' => $this->summary['total_amount'],
            'percentage' => $percentage,
            'timestamp' => now(),
        ], now()->addHours(24));
    }
}
