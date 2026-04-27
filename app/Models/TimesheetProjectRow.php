<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimesheetProjectRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'timesheet_id', 'project_code_id', 'project_name', 'row_order',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function projectCode(): BelongsTo
    {
        return $this->belongsTo(ProjectCode::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(TimesheetProjectHour::class, 'project_row_id');
    }
}
