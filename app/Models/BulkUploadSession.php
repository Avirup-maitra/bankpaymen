<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkUploadSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'failed_file_ids' => 'array',
        'upload_failed_files' => 'array',
        'file_ids' => 'array',
        'total_amount_processed' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BankFile::class);
    }

    public function refreshStats(): void
    {
        $files = $this->files();

        $fileIds = $files->pluck('id');
        $processed = (clone $files)->whereIn('status', ['PROCESSED', 'PARTIAL'])->count();
        $processing = (clone $files)->where('status', 'PROCESSING')->count();
        $failed = (clone $files)->where('status', 'REJECTED')->count();
        $uploadFailed = (int) ($this->upload_failed_count ?? 0);

        $this->update([
            'files_processed' => $processed,
            'files_processing' => $processing,
            'files_failed' => $failed + $uploadFailed,
            'total_rows_processed' => (clone $files)->sum('total_rows'),
            'total_rows_success' => (clone $files)->sum('success_rows'),
            'total_rows_rejected' => (clone $files)->sum('rejected_rows'),
            'total_amount_processed' => (clone $files)->sum('total_amount'),
            'failed_file_ids' => (clone $files)->where('status', 'REJECTED')->pluck('id')->values()->all(),
            'file_ids' => $fileIds->values()->all(),
            'status' => $this->statusFor($processed, $processing, $failed + $uploadFailed),
        ]);
    }

    private function statusFor(int $processed, int $processing, int $failed): string
    {
        $completed = $processed + $failed;

        if ($completed >= $this->total_files_uploaded) {
            return $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED';
        }

        if ($processing > 0 || $completed > 0) {
            return 'PROCESSING';
        }

        return 'QUEUED';
    }
}
