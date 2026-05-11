<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BankFile;
use App\Models\ProcessingError;
use Illuminate\Support\Facades\Storage;

$file = BankFile::latest()->first();

if (!$file) {
    echo "No file found.\n";
    exit;
}

echo "File ID: {$file->id}\n";
echo "Status: {$file->status}\n";
echo "Stored Path: {$file->stored_path}\n";

$fullPath = storage_path('app/' . $file->stored_path);
if (!file_exists($fullPath)) {
    // Try without 'app/' prefix if stored_path is absolute or weird
    if (file_exists($file->stored_path)) {
        $fullPath = $file->stored_path;
    } else {
        echo "File not found at: $fullPath\n";
    // Check other locations
        $fullPath = storage_path('app/public/' . $file->stored_path); 
    }
}

// Laravel 11 specific: check private folder
if (!file_exists($fullPath)) {
    $fullPath = storage_path('app/private/' . $file->stored_path);
}

if (file_exists($fullPath)) {
    echo "File Found. Content Snippet (first 1000 chars):\n";
    echo "--------------------------------------------------\n";
    echo substr(file_get_contents($fullPath), 0, 1000);
    echo "\n--------------------------------------------------\n";
} else {
    echo "File definitely not found at $fullPath\n";
}

echo "\nProcessing Errors:\n";
$errors = ProcessingError::where('bank_file_id', $file->id)->get();
foreach ($errors as $error) {
    echo "Row {$error->row_number}: [{$error->error_code}] {$error->error_message}\n";
    
    // Dump payload for this row
    $txn = App\Models\BankTransaction::where('bank_file_id', $file->id)
                ->where('row_number', $error->row_number)
                ->first();
    if ($txn) {
        $payload = is_string($txn->payload_json) ? json_decode($txn->payload_json, true) : $txn->payload_json;
        echo "   Payload keys: " . implode(', ', array_keys($payload ?? [])) . "\n";
        echo "   Amount Value: " . ($payload['amount'] ?? 'NULL') . "\n";
        echo "   Status Value: " . ($payload['status'] ?? 'NULL') . "\n";
        echo "   Full Payload: " . json_encode($payload) . "\n";
    }
}
