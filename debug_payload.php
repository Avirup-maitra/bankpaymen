<?php

use App\Models\BankFile;
use App\Models\BankTransaction;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lastFile = BankFile::latest()->first();

if (!$lastFile) {
    echo "No file found.\n";
    exit;
}

echo "File ID: {$lastFile->id}\n";

$txn = BankTransaction::where('bank_file_id', $lastFile->id)->first();

if ($txn) {
    echo "First Transaction Row: {$txn->row_number}\n";
    echo "Payload keys:\n";
    $payload = $txn->payload_json;
    if (is_string($payload)) $payload = json_decode($payload, true);
    
    print_r(array_keys($payload));
    
    echo "\nFull Payload:\n";
    print_r($payload);
} else {
    echo "No transactions found for this file.\n";
}
