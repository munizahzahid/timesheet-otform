<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id', 'entry_date', 'time_in', 'time_out',
        'hours_worked', 'reason', 'day_type',
        'is_ot', 'ot_hours', 'ot_start_time', 'ot_end_time', 'ot_type',
        'month', 'year',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'hours_worked' => 'decimal:2',
            'is_ot' => 'boolean',
            'ot_hours' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
