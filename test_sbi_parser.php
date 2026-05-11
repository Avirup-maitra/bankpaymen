<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BankFile;
use App\Models\BankTransaction;
use App\Imports\SBIBankFileImport;
use App\Services\BankFileService;

$file = BankFile::create([
    'original_filename' => 'sbi_test.txt',
    'stored_path' => 'public/sbi_test.txt',
    'source_type' => 'MANUAL',
    'bank_type' => 'SBI',
    'received_at' => now(),
    'status' => 'RECEIVED',
    'file_hash' => hash('sha256', time()),
]);

echo "Created file model ID: " . $file->id . "\n";

try {
    $importer = new SBIBankFileImport($file);
    $importer->import(base_path('public/sbi_test.txt'));
    echo "Import completed.\n";
    $file->refresh();
    echo "Success: " . $file->success_rows . ", Rejected: " . $file->rejected_rows . "\n";
    
    $tx = BankTransaction::where('bank_file_id', $file->id)->first();
    print_r($tx->toArray());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
