<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $table = 'pm_projects';

    protected $fillable = [
        'project_code',
        'project_name',
        'description',
        'status',
        'start_date_plan',
        'end_date_plan',
        'start_date_actual',
        'end_date_actual',
        'start_date_revise',
        'end_date_revise',
        'overall_plan_progress',
        'overall_actual_progress',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date_plan' => 'date',
            'end_date_plan' => 'date',
            'start_date_actual' => 'date',
            'end_date_actual' => 'date',
            'start_date_revise' => 'date',
            'end_date_revise' => 'date',
        ];
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('phase_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectProgressLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
