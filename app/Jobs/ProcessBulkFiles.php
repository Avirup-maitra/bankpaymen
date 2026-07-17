<?php

namespace App\Jobs;

use App\Models\BankFile;
use App\Models\BulkUploadSession;
use App\Services\BankFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBulkFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public array $fileIds;
    public ?int $bulkUploadSessionId;

    public function __construct(array $fileIds, ?int $bulkUploadSessionId = null)
    {
        $this->fileIds = $fileIds;
        $this->bulkUploadSessionId = $bulkUploadSessionId;
    }

    public function handle(BankFileService $service): void
    {
        $session = $this->bulkUploadSessionId
            ? BulkUploadSession::find($this->bulkUploadSessionId)
            : null;

        foreach ($this->fileIds as $fileId) {
            $bankFile = BankFile::find($fileId);
            if (!$bankFile || $bankFile->status !== 'RECEIVED') {
                continue;
            }

            try {
                $service->processFile($bankFile);
            } catch (\Exception $e) {
                Log::error("Error processing file {$fileId}: {$e->getMessage()}");
                $bankFile->update([
                    'status' => 'REJECTED',
                    'error_summary' => $e->getMessage(),
                ]);
            }

            $session?->refreshStats();
        }

        $session?->refreshStats();
    }
}
