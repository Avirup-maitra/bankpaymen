<?php

namespace App\Jobs;

use App\Models\BulkUploadSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CollectBulkUploadStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected string $sessionId;

    public function __construct(int $userId, string $sessionId)
    {
        $this->userId = $userId;
        $this->sessionId = $sessionId;
    }

    public function handle(): void
    {
        $session = BulkUploadSession::where('session_id', $this->sessionId)->first();

        if ($session) {
            $session->refreshStats();
            $session->refresh();

            Cache::put("bulk_upload_stats_{$this->sessionId}", [
                'session_id' => $session->session_id,
                'user_id' => $session->user_id,
                'timestamp' => now()->toIso8601String(),
                'files' => [
                    'processed' => $session->files_processed,
                    'processing' => $session->files_processing,
                    'rejected' => $session->files_failed,
                    'total_completed' => $session->files_processed + $session->files_failed,
                    'total_pending' => max(0, $session->total_files_uploaded - $session->files_processed - $session->files_failed),
                ],
                'transactions' => [
                    'total' => $session->total_rows_processed,
                    'success' => $session->total_rows_success,
                    'rejected' => $session->total_rows_rejected,
                ],
                'upload_session' => $session->toArray(),
            ], now()->addHours(24));
        }
    }
}
