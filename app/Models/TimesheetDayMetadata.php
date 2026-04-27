<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetDayMetadata extends Model
{
    protected $table = 'timesheet_day_metadata';

    protected $fillable = [
        'timesheet_id', 'entry_date', 'day_of_week', 'day_type',
        'available_hours', 'time_in', 'time_out',
        'late_hours', 'ot_eligible_hours', 'attendance_hours',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'available_hours' => 'decimal:1',
            'late_hours' => 'decimal:1',
            'ot_eligible_hours' => 'decimal:1',
            'attendance_hours' => 'decimal:1',
        ];
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
