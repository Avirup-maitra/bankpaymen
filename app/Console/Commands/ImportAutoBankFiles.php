<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankFile;
use App\Services\BankFileService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Jobs\ProcessBankFile;
use Illuminate\Http\UploadedFile;

class ImportAutoBankFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:import-auto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan inbox and import new bank files';

    /**
     * Execute the console command.
     */
    public function handle(BankFileService $service)
    {
        $inboxPath = config('app.bank_inbox_path', 'storage/app/bank_uploads/inbox'); // Ensure this config exists or use env
        // Using env directly or config. Best to use config. I'll use env for now as I didn't verify validation of config/app.php
        $inboxPath = env('BANK_INBOX_PATH', 'storage/app/get/inbox');
        
        // Fix path resolution
        if (!File::exists($inboxPath)) {
             // Try relative to base path
             $inboxPath = base_path($inboxPath);
             if (!File::exists($inboxPath)) {
                 $this->error("Inbox path does not exist: $inboxPath");
                 // Create it?
                 File::makeDirectory($inboxPath, 0755, true);
                 $this->info("Created inbox path: $inboxPath");
             }
        }

        $processedPath = base_path(env('BANK_PROCESSED_PATH', 'storage/app/bank_uploads/processed'));
        $rejectedPath = base_path(env('BANK_REJECTED_PATH', 'storage/app/bank_uploads/rejected'));

        if (!File::exists($processedPath)) File::makeDirectory($processedPath, 0755, true);
        if (!File::exists($rejectedPath)) File::makeDirectory($rejectedPath, 0755, true);

        $files = File::files($inboxPath);

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $ext = strtolower($file->getExtension());

            if ($ext !== 'xlsx' && $ext !== 'xls') {
                continue;
            }

            $this->info("Processing: $filename");

            try {
                $hash = hash_file('sha256', $file->getPathname());
                
                // Check duplicate
                $existing = BankFile::where('file_hash', $hash)->first();
                
                if ($existing) {
                    $this->warn("Skipping duplicate file (ID: {$existing->id}): $filename");
                    // Move to processed or rejected? Or duplicate folder? 
                    // Usually duplicate means we processed it. Move to processed to clear inbox?
                    // Safe to move to rejected or a 'duplicates' folder. 
                    // For now, I'll move to rejected with a note log? Or just leave it?
                    // Requirement: "For each file not already imported... import it".
                    // Does not specify what to do with duplicates in inbox.
                    // I'll move to rejected to avoid processing loop.
                    $newPath = $rejectedPath . '/' . $filename . '.duplicate_' . time();
                    File::move($file->getPathname(), $newPath);
                    continue;
                }

                // Create BankFile record
                // We fake an UploadedFile or just copy it to internal storage.
                // Service expects UploadedFile for handleUpload but that method does a lot of moving.
                // We should manually create the record and move file to our internal storage struct.
                
                // Copy to internal storage
                $internalPath = 'bank_uploads/' . date('Y/m') . '/' . $filename . '_' . time() . '.' . $ext;
                Storage::put($internalPath, File::get($file->getPathname()));
                
                $bankFile = BankFile::create([
                    'original_filename' => $filename,
                    'stored_path' => $internalPath,
                    'source_type' => 'AUTO',
                    'received_at' => now(),
                    'status' => 'RECEIVED',
                    'file_hash' => $hash,
                ]);

                // Run SYNC
                $service->processFile($bankFile);

                $bankFile->refresh();

                if (in_array($bankFile->status, ['PROCESSED', 'PARTIAL'])) {
                    $newPath = $processedPath . '/' . $filename;
                    // Handle collision
                    if (File::exists($newPath)) {
                        $newPath .= '_' . time();
                    }
                    File::move($file->getPathname(), $newPath);
                    $this->info("Imported and moved to Processed: $filename");
                } else {
                    $newPath = $rejectedPath . '/' . $filename;
                    if (File::exists($newPath)) {
                        $newPath .= '_' . time();
                    }
                    File::move($file->getPathname(), $newPath);
                    $this->error("Import Failed/Rejected. Moved to Rejected: $filename");
                }

            } catch (\Exception $e) {
                $this->error("Error processing $filename: " . $e->getMessage());
                // Move to rejected
                $newPath = $rejectedPath . '/' . $filename . '.error_' . time();
                File::move($file->getPathname(), $newPath);
            }
        }
    }
}
