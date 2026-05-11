<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBankFile;
use App\Models\BankFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessBankFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:process-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan inbox directory for new bank files and process them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $inboxPath = storage_path(config('bankfiles.inbox'));

            // Check if inbox directory exists
            if (!File::isDirectory($inboxPath)) {
                $this->info('ℹ️  Inbox directory does not exist: ' . $inboxPath);
                return self::SUCCESS;
            }

            // Get all files in inbox
            $files = File::files($inboxPath);

            if (empty($files)) {
                $this->info('✓ No files found in inbox. Scheduler will run again in 15 minutes.');
                Log::channel('bank_processing')->info('Scheduler check: No files in inbox', [
                    'timestamp' => now(),
                    'inbox_path' => $inboxPath
                ]);
                return self::SUCCESS;
            }

            $this->info('📁 Found ' . count($files) . ' file(s) in inbox. Processing...');

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $filePath = $file->getRealPath();

                // Generate SHA256 hash of file contents
                $fileHash = hash_file('sha256', $filePath);

                // Check if this file has already been processed
                $existingFile = BankFile::where('file_hash', $fileHash)->first();

                if ($existingFile) {
                    $this->warn('⚠️  Skipped (already processed): ' . $fileName);
                    Log::channel('bank_processing')->warning('Duplicate file skipped', [
                        'file_name' => $fileName,
                        'file_hash' => $fileHash,
                        'previous_id' => $existingFile->id,
                        'previous_status' => $existingFile->status
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Auto-detect bank type from filename
                // If filename contains 'SBI', treat as SBI, otherwise default to ICICI
                $bankType = 'ICICI'; // Default
                if (stripos($fileName, 'sbi') !== false) {
                    $bankType = 'SBI';
                } elseif (stripos($fileName, 'icici') !== false) {
                    $bankType = 'ICICI';
                }

                // Create new BankFile record
                $bankFile = BankFile::create([
                    'original_filename' => $fileName,
                    'stored_path' => config('bankfiles.inbox') . '/' . $fileName,
                    'source_type' => 'AUTO',
                    'bank_type' => $bankType, // ← NOW INCLUDES BANK TYPE
                    'received_at' => now(),
                    'status' => 'RECEIVED',
                    'file_hash' => $fileHash,
                ]);

                // Dispatch processing job
                ProcessBankFile::dispatch($bankFile);

                $this->info('✓ Processing started: ' . $fileName . ' (Bank: ' . $bankType . ', ID: ' . $bankFile->id . ')');
                Log::channel('bank_processing')->info('File processing initiated', [
                    'file_id' => $bankFile->id,
                    'file_name' => $fileName,
                    'bank_type' => $bankType,
                    'file_hash' => $fileHash,
                    'received_at' => $bankFile->received_at
                ]);

                $processedCount++;
            }

            $this->line('');
            $this->info('✅ Processing summary:');
            $this->line('   Processed: ' . $processedCount);
            $this->line('   Skipped (duplicates): ' . $skippedCount);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::channel('bank_processing')->error('Bank file processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
