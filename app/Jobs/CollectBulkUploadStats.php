<?php

namespace App\Jobs;

use App\Models\BankFile;
use App\Models\BankTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CollectBulkUploadStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected string $sessionId;
    protected Carbon $startTime;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $sessionId)
    {
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->startTime = now();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get files uploaded in this session
        $cacheKey = "bulk_upload_{$this->sessionId}";
        $uploadData = Cache::get($cacheKey, [
            'files_to_process' => 0,
            'files_uploaded' => 0,
            'files_failed' => 0,
        ]);

        // Get statistics from database
        $query = BankFile::where('created_by', $this->userId)
            ->where('source_type', 'MANUAL');

        // Get file statistics
        $filesProcessed = $query->where('status', 'PROCESSED')->count();
        $filesPartial = $query->where('status', 'PARTIAL')->count();
        $filesRejected = $query->where('status', 'REJECTED')->count();
        $filesReceived = $query->where('status', 'RECEIVED')->count();
        $filesProcessing = $query->where('status', 'PROCESSING')->count();

        // Get transaction statistics
        $totalTransactions = BankTransaction::whereIn('bank_file_id', 
            $query->pluck('id')
        )->count();

        $successTransactions = BankTransaction::whereIn('bank_file_id', 
            $query->pluck('id')
        )->where('import_status', 'OK')->count();

        $rejectedTransactions = BankTransaction::whereIn('bank_file_id', 
            $query->pluck('id')
        )->where('import_status', 'REJECTED')->count();

        // Store comprehensive statistics
        $stats = [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
            
            // File Statistics
            'files' => [
                'processed' => $filesProcessed,
                'partial' => $filesPartial,
                'rejected' => $filesRejected,
                'received' => $filesReceived,
                'processing' => $filesProcessing,
                'total_completed' => $filesProcessed + $filesPartial + $filesRejected,
                'total_pending' => $filesReceived + $filesProcessing,
            ],
            
            // Transaction Statistics
            'transactions' => [
                'total' => $totalTransactions,
                'success' => $successTransactions,
                'rejected' => $rejectedTransactions,
            ],
            
            // Upload Session Data
            'upload_session' => $uploadData,
        ];

        // Cache stats for 24 hours
        Cache::put("bulk_upload_stats_{$this->sessionId}", $stats, now()->addHours(24));
    }
}
