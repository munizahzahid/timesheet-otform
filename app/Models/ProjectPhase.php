<?php

namespace App\Models;

use App\Services\ProjectProgressCalculator;
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

    /**
     * Calculate how many days the actual progress is behind the plan progress.
     * Falls back to subtask plan dates when the phase itself has no plan dates.
     * Returns 0 if on time or ahead.
     */
    public function getDelayDaysAttribute(): int
    {
        $start = $this->start_date_plan;
        $end = $this->end_date_plan;

        if (!$start || !$end) {
            $tasks = $this->tasks;
            foreach ($tasks as $task) {
                if ($task->start_date_plan && (!$start || $task->start_date_plan->lt($start))) {
                    $start = $task->start_date_plan;
                }
                if ($task->end_date_plan && (!$end || $task->end_date_plan->gt($end))) {
                    $end = $task->end_date_plan;
                }
            }
        }

        return (new ProjectProgressCalculator())->calculateDelayDays(
            $start,
            $end,
            (int) $this->progress_plan,
            (int) $this->progress_actual
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectProgressLog::class);
    }
}
