<?php

use App\Models\BankFile;
use App\Models\BankTransaction;
use Carbon\Carbon;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Bank Files ---\n";
echo "Total: " . BankFile::count() . "\n";
echo "Statuses: " . json_encode(BankFile::select('status', \DB::raw('count(*) as count'))->groupBy('status')->pluck('count', 'status')) . "\n";

$lastFile = BankFile::latest()->first();
if ($lastFile) {
    echo "Last File ID: {$lastFile->id} ({$lastFile->created_at})\n";
    echo "Status: {$lastFile->status}\n";
    echo "Summary (DB): Success={$lastFile->success_rows}, Rejected={$lastFile->rejected_rows}, Total={$lastFile->total_rows}\n";
}

echo "\n--- Transactions ---\n";
echo "Total OK: " . BankTransaction::where('import_status', 'OK')->count() . "\n";
echo "Total REJECTED: " . BankTransaction::where('import_status', 'REJECTED')->count() . "\n";

$okTxns = BankTransaction::where('import_status', 'OK')->limit(5)->get();
if ($okTxns->count() > 0) {
    echo "Sample OK Transactions:\n";
    foreach ($okTxns as $txn) {
        echo "ID: {$txn->id} | Date: {$txn->transaction_date} | Liquidation: {$txn->liquidation_date} | Amount: {$txn->amount} | Status: {$txn->bank_status}\n";
    }
} else {
    echo "No OK transactions found.\n";
}

echo "\n--- Monthly Trend Query Test ---\n";
$startDate = Carbon::today()->subMonths(11)->startOfMonth();
echo "Start Date: $startDate\n";

try {
    $monthlyTrend = BankTransaction::where('import_status', 'OK')
        ->where(function ($q) {
            $q->whereIn('bank_status', ['Paid', 'PAID', 'paid']);
        })
        ->where(function ($q) use ($startDate) {
            $q->whereDate('liquidation_date', '>=', $startDate)
              ->orWhere(function ($q2) use ($startDate) {
                  $q2->whereNull('liquidation_date')
                     ->whereDate('transaction_date', '>=', $startDate);
              });
        })
        ->selectRaw("strftime('%Y-%m', COALESCE(liquidation_date, transaction_date)) as month, SUM(amount) as total")
        ->groupBy('month')
        ->orderBy('month')
        ->get();
    
    echo "Query Result:\n";
    print_r($monthlyTrend->toArray());
} catch (\Exception $e) {
    echo "Query Error: " . $e->getMessage() . "\n";
}
