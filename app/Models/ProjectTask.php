<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    protected $fillable = [
        'project_id',
        'phase_id',
        'predecessor_task_id',
        'task_name',
        'task_order',
        'assigned_to',
        'progress_plan',
        'progress_actual',
        'progress_revise',
        'weight',
        'start_date_plan',
        'end_date_plan',
        'start_date_actual',
        'end_date_actual',
        'start_date_revise',
        'end_date_revise',
        'status',
        'remarks',
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

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function predecessorTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'predecessor_task_id');
    }

    public function successorTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'predecessor_task_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectProgressLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class, 'project_task_id')->orderBy('created_at', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectTaskAttachment::class, 'project_task_id')->orderBy('created_at', 'desc');
    }
}
