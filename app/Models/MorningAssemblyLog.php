<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MorningAssemblyLog extends Model
{
    protected $table = 'morning_assembly_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'assembly_date', 'attended', 'source',
    ];

    protected function casts(): array
    {
        return [
            'assembly_date' => 'date',
            'attended' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
