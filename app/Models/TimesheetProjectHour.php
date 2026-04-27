<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetProjectHour extends Model
{
    protected $fillable = [
        'project_row_id', 'entry_date',
        'normal_nc_hours', 'normal_cobq_hours',
        'ot_nc_hours', 'ot_cobq_hours',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'normal_nc_hours' => 'decimal:1',
            'normal_cobq_hours' => 'decimal:1',
            'ot_nc_hours' => 'decimal:1',
            'ot_cobq_hours' => 'decimal:1',
        ];
    }

    public function projectRow(): BelongsTo
    {
        return $this->belongsTo(TimesheetProjectRow::class, 'project_row_id');
    }
}
