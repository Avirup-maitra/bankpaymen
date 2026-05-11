<?php

use App\Models\BankTransaction;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Bank Status values...\n";

// Get unique statuses
$statuses = BankTransaction::select('bank_status')->distinct()->get();

foreach ($statuses as $s) {
    $val = $s->bank_status;
    echo "Value: '$val' | Length: " . strlen($val) . " | Hex: " . bin2hex($val) . "\n";
}

echo "\nChecking Count with strict 'Paid':\n";
echo "Count: " . BankTransaction::where('bank_status', 'Paid')->count() . "\n";

echo "Checking Count with TRIM:\n";
// SQLite specific TRIM
echo "Count (TRIM): " . BankTransaction::whereRaw("TRIM(bank_status) = 'Paid'")->count() . "\n";

echo "Checking Count with LIKE:\n";
echo "Count (LIKE): " . BankTransaction::where('bank_status', 'LIKE', 'Paid%')->count() . "\n";
