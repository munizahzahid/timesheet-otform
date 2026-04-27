<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetAdminHour extends Model
{
    protected $fillable = [
        'timesheet_id', 'admin_type', 'entry_date', 'hours',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'hours' => 'decimal:1',
        ];
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
