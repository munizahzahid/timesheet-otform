<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtFormEntry extends Model
{
    protected $fillable = [
        'ot_form_id', 'entry_date', 'project_code_id', 'project_name',
        'planned_start_time', 'planned_end_time', 'planned_total_hours',
        'actual_start_time', 'actual_end_time', 'actual_total_hours',
        'ot_normal_day_hours', 'ot_rest_day_hours', 'ot_rest_day_excess_hours', 'ot_rest_day_count', 'ot_ph_hours',
        'meal_break', 'over_3_hours', 'is_shift', 'is_public_holiday', 'ot_type',
        'jenis_ot_normal', 'jenis_ot_training', 'jenis_ot_kaizen', 'jenis_ot_5s',
        'ot_rate_1', 'ot_rate_2', 'ot_rate_3', 'ot_rate_4', 'ot_rate_5',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'planned_total_hours' => 'decimal:2',
            'actual_total_hours' => 'decimal:2',
            'ot_normal_day_hours' => 'decimal:2',
            'ot_rest_day_hours' => 'decimal:2',
            'ot_rest_day_excess_hours' => 'decimal:2',
            'ot_rest_day_count' => 'integer',
            'ot_ph_hours' => 'decimal:2',
            'meal_break' => 'boolean',
            'over_3_hours' => 'boolean',
            'is_shift' => 'boolean',
            'is_public_holiday' => 'boolean',
            'jenis_ot_normal' => 'boolean',
            'jenis_ot_training' => 'boolean',
            'jenis_ot_kaizen' => 'boolean',
            'jenis_ot_5s' => 'boolean',
            'ot_rate_1' => 'decimal:2',
            'ot_rate_2' => 'decimal:2',
            'ot_rate_3' => 'decimal:2',
            'ot_rate_4' => 'decimal:2',
            'ot_rate_5' => 'decimal:2',
        ];
    }

    public function otForm(): BelongsTo
    {
        return $this->belongsTo(OtForm::class);
    }

    public function projectCode(): BelongsTo
    {
        return $this->belongsTo(ProjectCode::class);
    }
}
