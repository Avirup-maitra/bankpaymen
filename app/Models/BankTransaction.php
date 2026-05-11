<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'liquidation_date' => 'datetime',
        'payload_json' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(BankFile::class, 'bank_file_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ProcessingError::class);
    }
}
