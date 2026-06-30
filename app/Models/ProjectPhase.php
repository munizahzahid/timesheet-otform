<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhase extends Model
{
    protected $fillable = [
        'project_id',
        'phase_name',
        'phase_order',
        'start_date_plan',
        'end_date_plan',
        'start_date_actual',
        'end_date_actual',
        'start_date_revise',
        'end_date_revise',
        'progress_plan',
        'progress_actual',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'phase_id')->orderBy('task_order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectProgressLog::class);
    }
}
