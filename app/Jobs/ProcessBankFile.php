<?php

namespace App\Jobs;

use App\Models\BankFile;
use App\Services\BankFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBankFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public BankFile $bankFile;

    /**
     * Create a new job instance.
     */
    public function __construct(BankFile $bankFile)
    {
        $this->bankFile = $bankFile;
    }

    /**
     * Execute the job.
     */
    public function handle(BankFileService $service): void
    {
        $service->processFile($this->bankFile);
    }
}
