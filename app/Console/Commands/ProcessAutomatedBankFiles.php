<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessAutomatedBankFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:process-automated';
    protected $description = 'Scans the storage/app/bank_files/input directories for files and processes them.';

    public function handle(\App\Services\BankFileService $service)
    {
        $this->info("Starting automated bank file processing...");
        
        $this->processDirectory('icici', 'ICICI', $service);
        $this->processDirectory('sbi', 'SBI', $service);
        
        $this->info("Automated processing completed.");
    }

    protected function processDirectory($folderName, $bankType, $service)
    {
        $inputDir = storage_path("app/bank_files/input/{$folderName}");
        $archiveDir = storage_path("app/bank_files/archive");

        if (!is_dir($inputDir)) {
            @mkdir($inputDir, 0755, true);
        }
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0755, true);
        }

        $files = glob($inputDir . '/*.*');
        
        if (empty($files)) {
            $this->info("No files found in {$folderName} folder.");
            return;
        }

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $this->info("Processing: {$filename}");

            try {
                // Mock an UploadedFile to use the existing service. 
                // test mode = true bypasses is_uploaded_file check for CLI.
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $filePath,
                    $filename,
                    mime_content_type($filePath) ?: 'application/octet-stream',
                    null,
                    true 
                );

                $bankFile = $service->handleUpload($uploadedFile, null, 'AUTO', $bankType);
                
                // Immediately process since we're in CLI background anyway
                $service->processFile($bankFile);
                
                $this->info("Successfully processed and imported: {$filename}");
                
                // Move original to archive
                rename($filePath, $archiveDir . '/' . uniqid() . '_' . $filename);
                
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                     $this->error("Duplicate file detected: {$filename}. Moving to archive.");
                     rename($filePath, $archiveDir . '/dup_' . uniqid() . '_' . $filename);
                } else {
                     $this->error("Failed to process {$filename}: " . $e->getMessage());
                     // We leave it in input folder or move to an error folder? 
                     // Moving to archive to prevent endless loops.
                     rename($filePath, $archiveDir . '/error_' . uniqid() . '_' . $filename);
                }
            }
        }
    }
}
