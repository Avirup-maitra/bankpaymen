<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingError extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(BankFile::class, 'bank_file_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }
}
