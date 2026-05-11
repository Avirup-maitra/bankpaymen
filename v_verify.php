<?php

use App\Models\User;
use App\Models\BankFile;
use App\Services\BankFileService;
use App\Constants\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

// 1. Create Admin User
$admin = User::firstOrCreate(
    ['email' => 'admin@example.com'],
    [
        'name' => 'Admin User',
        'password' => bcrypt('password'),
        'role' => Role::ADMIN,
        'is_active' => true,
    ]
);
echo "Admin user created/found: {$admin->email}\n";

// 2. Create Sample Excel File
$fileName = 'test_bank_file.xlsx';
// We can't easily create a valid Excel file with raw PHP without phpspreadsheet logic here.
// But we used BankFileService which uses Maatwebsite which uses PhpSpreadsheet.
// Let's copy a dummy file if user has one, or skip full import test and just test the service with a mock?
// Constructing a real xlsx is hard in a script. 
// Let's create a CSV and name it xlsx? No, reader will fail.
// I'll create a simple XLSX using PhpSpreadsheet since we have it.

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers Row 2
$headers = [
    'Transaction type', 'Amount', 'Debit Account no', 'IFSC', 'Beneficiary Account No', 
    'Beneficiary Name', 'Remarks for Client', 'Remarks for Beneficiary', 'Transaction_id', 
    'Transaction_Date', 'Invoice_id', 'Invoice_id and Date', 'token_id', 'Email_id', 
    'Phone', 'Source File Name', 'File Name', 'Payment Ref No', 'Status', 
    'Liquidation Date', 'Customer Ref No', 'Instrument_No', 'UTR / Bank Remarks', 
    'Maker ID', 'First Approver', 'Second Approver'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '2', $header);
    $col++;
}

// Data Row 4
$data = [
    'NEFT', 1000.50, '1234567890', 'ICIC0001234', '0987654321', 
    'John Doe', 'Payment', 'Remark', 'TXN123', 
    '2023-10-27', 'INV001', 'INV001 2023-10-27', 'TOK1', 'john@example.com', 
    '9999999999', 'source.xlsx', 'FileA', 'REF12345', 'Paid', 
    '2023-10-28', 'CUST001', 'INST01', 'UTR001 / OK', 
    'MK1', 'AP1', 'AP2'
];

$col = 'A';
foreach ($data as $val) {
    $sheet->setCellValue($col . '4', $val);
    $col++;
}

$writer = new Xlsx($spreadsheet);
$tempPath = storage_path('app/test_import.xlsx');
$writer->save($tempPath);

echo "Created test Excel file at $tempPath\n";

// 3. Test Service Upload & Process
$service = app(BankFileService::class);
$uploadedFile = new UploadedFile($tempPath, 'test_import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

try {
    $bankFile = $service->handleUpload($uploadedFile, $admin, 'MANUAL');
    echo "File uploaded successfully. ID: {$bankFile->id}, Path: {$bankFile->stored_path}\n";

    // Run Process
    echo "Processing file...\n";
    $service->processFile($bankFile);
    $bankFile->refresh();
    echo "Process complete. Status: {$bankFile->status}, Success: {$bankFile->success_rows}, Rejected: {$bankFile->rejected_rows}\n";

    if ($bankFile->success_rows == 1) {
        echo "VERIFICATION PASSED: Import worked.\n";
    } else {
        echo "VERIFICATION FAILED: Expected 1 success row.\n";
    }

} catch (\Exception $e) {
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
}

// 4. Test Auto Import Command
// Put file in inbox
$inboxPath = storage_path('app/bank_uploads/inbox');
if (!File::exists($inboxPath)) File::makeDirectory($inboxPath, 0755, true);
$autoFile = $inboxPath . '/auto_test.xlsx';

// Create another file with different content to avoid hash collision or clean DB first
// Or just copy and append time to ref no in the cell?
$sheet->setCellValue('R4', 'REF_AUTO_' . time());
$writer->save($autoFile);

echo "Placed file in inbox: $autoFile\n";
echo "Running bank:import-auto...\n";

Artisan::call('bank:import-auto');
$output = Artisan::output();
echo "Command Output: $output\n";

// 5. Test Export Command
echo "Running bank:export-daily...\n";
Artisan::call('bank:export-daily', ['--date' => date('Y-m-d')]); // Today might not match the date in file (2023-10-27)
// Wait, the file has Liquidation Date 2023-10-28.
// So let's run for 2023-10-28.
Artisan::call('bank:export-daily', ['--date' => '2023-10-28']);
$outputExport = Artisan::output();
echo "Export Output: $outputExport\n";
