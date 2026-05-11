<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportsLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'exports_log';

    protected $casts = [
        'export_date' => 'date',
    ];
}
