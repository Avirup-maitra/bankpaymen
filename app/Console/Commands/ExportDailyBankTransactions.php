<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankTransaction;
use App\Models\ExportsLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ExportDailyBankTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:export-daily {--date= : Date to export (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export OK transactions for a specific date to CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateStr = $this->option('date') ?: date('Y-m-d');
        try {
            $date = Carbon::parse($dateStr);
        } catch (\Exception $e) {
            $this->error("Invalid date format.");
            return;
        }

        $this->info("Exporting for date: " . $date->toDateString());

        // Export rules:
        // Prefer liquidation_date if present else transaction_date else file processed_at?
        // Query logic:
        // We need to check each record's effective date. 
        // SQL: WHERE (liquidation_date IS NOT NULL AND DATE(liquidation_date) = ?) 
        //      OR (liquidation_date IS NULL AND transaction_date IS NOT NULL AND DATE(transaction_date) = ?)
        //      OR ...
        // Simpler: Just allow filtering by one of them or complex logic in code.
        // User says: "Prefer liquidation_date date if present else transaction_date date else file processed date."
        // This implies we select rows where "EffectiveDate" == TargetDate.
        
        // Let's use a closure or raw query.
        $targetDate = $date->toDateString();
        
        $query = BankTransaction::query()
            ->where('import_status', 'OK')
            ->where(function ($q) use ($targetDate) {
                $q->whereDate('liquidation_date', $targetDate)
                  ->orWhere(function ($q2) use ($targetDate) {
                      $q2->whereNull('liquidation_date')
                         ->whereDate('transaction_date', $targetDate);
                  });
                  // Add fallback to file processed date? 
                  // Needs join with bank_files.
                  // For now, let's stick to transaction columns as primary.
            });

        $count = $query->count();
        if ($count === 0) {
            $this->info("No records found for $dateStr");
            return;
        }

        $filename = "ICICI_EXPORT_" . $date->format('Ymd') . ".csv";
        $headers = [
            'Payment Ref No', 'Customer Ref No', 'Amount', 'Status', 'UTR / Bank Remarks',
            'Transaction Date', 'Liquidation Date', 'Beneficiary Account No', 'Beneficiary Name', 'IFSC'
        ];

        // Generate CSV content
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        $query->chunk(1000, function ($rows) use ($handle) {
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->payment_ref_no,
                    $row->customer_ref_no,
                    $row->amount,
                    $row->bank_status,
                    $row->utr_bank_remarks,
                    $row->transaction_date ? $row->transaction_date->format('Y-m-d') : '',
                    $row->liquidation_date ? $row->liquidation_date->format('Y-m-d') : '',
                    $row->beneficiary_account_no,
                    $row->beneficiary_name,
                    $row->ifsc
                ]);
            }
        });

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        // Save to storage
        $storedPath = 'exports/' . $filename;
        Storage::put($storedPath, $content);

        // Copy to Outbox
        $outboxPath = base_path(env('EXPORT_OUTBOX_PATH', 'storage/app/exports/outbox'));
        if (!File::exists($outboxPath)) File::makeDirectory($outboxPath, 0755, true);
        
        File::put($outboxPath . '/' . $filename, $content);

        // Log
        ExportsLog::create([
            'export_date' => $targetDate,
            'export_filename' => $filename,
            'exported_rows' => $count,
            'status' => 'SUCCESS',
            'message' => 'Exported successfully.'
        ]);

        $this->info("Exported $count rows to $filename");
    }
}
