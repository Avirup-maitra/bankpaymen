<?php

namespace App\Jobs;

use App\Models\BankFile;
use App\Services\BankFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ProcessBulkFiles - Process multiple bank files in a single job
 * This is more efficient than dispatching individual jobs for large batches
 */
class ProcessBulkFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Array of BankFile IDs to process
     * @var array
     */
    public array $fileIds;

    /**
     * Create a new job instance.
     */
    public function __construct(array $fileIds)
    {
        $this->fileIds = $fileIds;
        // Higher timeout for batch processing
        $this->timeout = 3600; // 1 hour
    }

    /**
     * Execute the job - process all files in batch
     */
    public function handle(BankFileService $service): void
    {
        foreach ($this->fileIds as $fileId) {
            // Check if file exists and is in RECEIVED state
            $bankFile = BankFile::find($fileId);
            if (!$bankFile || $bankFile->status !== 'RECEIVED') {
                continue;
            }

            try {
                $service->processFile($bankFile);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error(
                    "Error processing file {$fileId}: {$e->getMessage()}"
                );
                // Don't fail the entire batch, continue with next file
            }
        }
    }
}
