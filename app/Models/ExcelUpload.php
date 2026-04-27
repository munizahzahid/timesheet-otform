<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelUpload extends Model
{
    protected $fillable = [
        'user_id', 'file_name', 'file_path', 'month', 'year',
        'rows_parsed', 'rows_failed',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
