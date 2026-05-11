<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\BankFile;
use App\Services\BankFileService;
use App\Constants\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VerifyBankSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:verify-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the bank app setup by running a test import flow';

    /**
     * Execute the console command.
     */
    public function handle(BankFileService $service)
    {
        $this->info("Starting Verification...");

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
        $this->info("Admin user check: OK ({$admin->email})");

        // 2. Create Sample Excel File
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
        $testRef = 'REF_' . time();
        $data = [
            'NEFT', 1000.50, '1234567890', 'ICIC0001234', '0987654321', 
            'John Doe', 'Payment', 'Remark', 'TXN123', 
            '2023-10-27', 'INV001', 'INV001 2023-10-27', 'TOK1', 'john@example.com', 
            '9999999999', 'source.xlsx', 'FileA', $testRef, 'Paid', 
            '2023-10-28', 'CUST001', 'INST01', 'UTR001 / OK', 
            'MK1', 'AP1', 'AP2'
        ];

        $col = 'A';
        foreach ($data as $val) {
            $sheet->setCellValue($col . '4', $val);
            $col++;
        }

        $tempPath = storage_path('app/test_import_' . time() . '.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $this->info("Created test Excel file at $tempPath");

        // 3. Test Service Upload & Process
        $uploadedFile = new UploadedFile($tempPath, basename($tempPath), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        try {
            $bankFile = $service->handleUpload($uploadedFile, $admin, 'MANUAL');
            $this->info("File uploaded successfully. ID: {$bankFile->id}, Path: {$bankFile->stored_path}");

            // Run Process
            $this->info("Processing file...");
            $service->processFile($bankFile);
            $bankFile->refresh();
            
            $this->info("Process complete. Status: {$bankFile->status}, Success: {$bankFile->success_rows}, Rejected: {$bankFile->rejected_rows}");

            if ($bankFile->success_rows == 1 && $bankFile->status == 'PROCESSED') {
                $this->info("VERIFICATION PASSED: Manual Import worked.");
            } else {
                $this->error("VERIFICATION FAILED: Expected 1 success row, got {$bankFile->success_rows}. Status: {$bankFile->status}");
                if ($bankFile->error_summary) {
                    $this->error("Error Summary: " . $bankFile->error_summary);
                }
            }

        } catch (\Exception $e) {
            $this->error("VERIFICATION FAILED: " . $e->getMessage());
        }

        // 4. Test Auto Import Command
        // Create another file
        $inboxPath = config('app.bank_inbox_path') ?? storage_path('app/bank_uploads/inbox');
        if (!File::exists($inboxPath)) {
             // Fallback to env or logical path if config is null
             $inboxPath = storage_path('app/bank_uploads/inbox');
        }
        if (!File::exists($inboxPath)) File::makeDirectory($inboxPath, 0755, true);
        
        $autoFile = $inboxPath . '/auto_test_' . time() . '.xlsx';
        $sheet->setCellValue('R4', 'REF_AUTO_' . time()); // Payment Ref
        $writer->save($autoFile);

        $this->info("Placed file in inbox: $autoFile");
        $this->info("Running bank:import-auto...");

        $exitCode = Artisan::call('bank:import-auto');
        $this->info("Command Output: " . Artisan::output());
        
        if ($exitCode === 0) {
             $this->info("Auto Import Command Ran Successfully.");
        } else {
             $this->error("Auto Import Command Failed.");
        }

        // 5. Test Export Command
        $this->info("Running bank:export-daily...");
        // Use the date from our first file 2023-10-27 (Liquidation 2023-10-28)
        $exitCode = Artisan::call('bank:export-daily', ['--date' => '2023-10-28']);
        $this->info("Export Output: " . Artisan::output());
        
        if ($exitCode === 0) {
             $this->info("Export Command Ran Successfully.");
        } else {
             $this->error("Export Command Failed.");
        }
    }
}
