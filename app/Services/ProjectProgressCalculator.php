<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectTask;
use Carbon\Carbon;

class ProjectProgressCalculator
{
    /**
     * Calculate the plan progress of a single task based on today's position
     * relative to its plan start and end dates.
     * Returns 0-100.
     */
    public function calculateTaskPlanProgress(ProjectTask $task): int
    {
        if (!$task->start_date_plan || !$task->end_date_plan) {
            return 0;
        }

        $today = Carbon::today();
        $start = $task->start_date_plan->copy()->startOfDay();
        $end = $task->end_date_plan->copy()->startOfDay();

        if ($today->lte($start)) {
            return 0;
        }

        if ($today->gte($end)) {
            return 100;
        }

        $totalDays = $start->diffInDays($end);
        if ($totalDays <= 0) {
            return 100;
        }

        $elapsed = $start->diffInDays($today);
        return (int) round(($elapsed / $totalDays) * 100);
    }

    /**
     * Calculate how many days the actual progress is behind the plan progress.
     *
     * Example: 5-day project, plan = 60%, actual = 20%.
     * Progress per day = 100% / 5 days = 20% per day.
     * Delay % = 60% - 20% = 40%.
     * Delay days = 40% / 20% = 2 days.
     *
     * @return int 0 if on time or ahead
     */
    public function calculateDelayDays(?Carbon $startDate, ?Carbon $endDate, int $planProgress, int $actualProgress): int
    {
        if (!$startDate || !$endDate || $actualProgress >= $planProgress) {
            return 0;
        }

        $totalDays = $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
        if ($totalDays <= 0) {
            $totalDays = 1;
        }

        $delayPercentage = $planProgress - $actualProgress;
        $progressPerDay = 100 / $totalDays;

        return (int) round($delayPercentage / $progressPerDay);
    }

    /**
     * Calculate and persist the progress of a phase.
     *
     * Plan progress: time-based based on phase plan dates (elapsed days / total plan days).
     * Actual progress: weighted average of task actual progress (by task weight).
     */
    public function recalculatePhaseProgress(ProjectPhase $phase): void
    {
        $tasks = $phase->tasks;
        $totalWeight = $tasks->sum('weight');

        // Actual progress: task-weighted (unchanged)
        if ($totalWeight <= 0) {
            $phase->progress_actual = 0;
        } else {
            $weightedActual = $tasks->sum(fn (ProjectTask $task) => $task->progress_actual * $task->weight);
            $phase->progress_actual = (int) round($weightedActual / $totalWeight);
        }

        // Plan progress: task-weighted based on each task's time-based plan progress
        if ($totalWeight <= 0) {
            $phase->progress_plan = 0;
        } else {
            $weightedPlan = $tasks->sum(fn (ProjectTask $task) => $this->calculateTaskPlanProgress($task) * $task->weight);
            $phase->progress_plan = (int) round($weightedPlan / $totalWeight);
        }

        // Derive phase actual dates from task actual dates
        $startedTasks = $tasks->whereNotNull('start_date_actual');
        $phase->start_date_actual = $startedTasks->isNotEmpty()
            ? $startedTasks->pluck('start_date_actual')->sortBy(fn ($d) => $d->getTimestamp())->first()
            : null;

        $tasksWithEnd = $tasks->whereNotNull('end_date_actual');
        $phase->end_date_actual = ($tasks->isNotEmpty() && $tasksWithEnd->count() === $tasks->count())
            ? $tasksWithEnd->pluck('end_date_actual')->sortBy(fn ($d) => $d->getTimestamp())->last()
            : null;

        $phase->save();
    }

    /**
     * Calculate and persist the project progress.
     *
     * Plan progress: weighted average of phase plan progress, weighted by phase plan days.
     * Actual progress: weighted average of all task actual progress (by task weight).
     */
    public function recalculateProjectProgress(Project $project): void
    {
        $tasks = $project->tasks;

        // Keep each task's stored plan progress in sync (used by task show view and reports)
        foreach ($tasks as $task) {
            $newPlanProgress = $this->calculateTaskPlanProgress($task);
            if ((int) $task->progress_plan !== $newPlanProgress) {
                $task->progress_plan = $newPlanProgress;
                $task->saveQuietly();
            }
        }

        $totalWeight = $tasks->sum('weight');

        // Actual progress: task-weighted (unchanged)
        if ($totalWeight <= 0) {
            $project->overall_actual_progress = 0;
        } else {
            $weightedActual = $tasks->sum(fn (ProjectTask $task) => $task->progress_actual * $task->weight);
            $project->overall_actual_progress = (int) round($weightedActual / $totalWeight);
        }

        // Plan progress: weighted average of phase plan progress + standalone tasks, weighted by plan days
        $items = [];
        $totalPlanDays = 0;

        foreach ($project->phases as $phase) {
            if ($phase->start_date_plan && $phase->end_date_plan) {
                $days = $phase->start_date_plan->diffInDays($phase->end_date_plan) + 1;
                $items[] = ['progress' => $phase->progress_plan ?? 0, 'weight' => $days];
                $totalPlanDays += $days;
            }
        }

        foreach ($tasks->whereNull('phase_id') as $task) {
            if ($task->start_date_plan && $task->end_date_plan) {
                $days = $task->start_date_plan->diffInDays($task->end_date_plan) + 1;
                $items[] = ['progress' => $this->calculateTaskPlanProgress($task), 'weight' => $days];
                $totalPlanDays += $days;
            }
        }

        if ($totalPlanDays > 0) {
            $weightedPlan = collect($items)->sum(fn (array $item) => $item['progress'] * $item['weight'] / $totalPlanDays);
            $project->overall_plan_progress = (int) round($weightedPlan);
        } else {
            // Fallback: task-weighted plan progress when no plan dates exist
            if ($totalWeight <= 0) {
                $project->overall_plan_progress = 0;
            } else {
                $weightedPlan = $tasks->sum(fn (ProjectTask $task) => $this->calculateTaskPlanProgress($task) * $task->weight);
                $project->overall_plan_progress = (int) round($weightedPlan / $totalWeight);
            }
        }

        $project->save();
    }

    /**
     * Recalculate the owning phase and project progress after a task change.
     */
    public function recalculateFromTask(ProjectTask $task): void
    {
        $project = $task->project;

        if ($task->phase_id) {
            $phase = ProjectPhase::find($task->phase_id);
            if ($phase) {
                $this->recalculatePhaseProgress($phase);
            }
        }

        if ($project) {
            $this->recalculateProjectProgress($project);
        }
    }

    /**
     * Recalculate every phase and project progress.
     */
    public function recalculateAll(Project $project): void
    {
        foreach ($project->phases as $phase) {
            $this->recalculatePhaseProgress($phase);
        }

        $this->recalculateProjectProgress($project);
    }
}
