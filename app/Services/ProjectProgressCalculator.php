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
     * Calculate and persist the weighted actual and plan progress of a phase.
     */
    public function recalculatePhaseProgress(ProjectPhase $phase): void
    {
        $tasks = $phase->tasks;
        $totalWeight = $tasks->sum('weight');

        if ($totalWeight <= 0) {
            $phase->progress_plan = 0;
            $phase->progress_actual = 0;
        } else {
            $weightedPlan = $tasks->sum(fn (ProjectTask $task) => $this->calculateTaskPlanProgress($task) * $task->weight);
            $phase->progress_plan = (int) round($weightedPlan / $totalWeight);

            $weightedActual = $tasks->sum(fn (ProjectTask $task) => $task->progress_actual * $task->weight);
            $phase->progress_actual = (int) round($weightedActual / $totalWeight);
        }

        $phase->save();
    }

    /**
     * Calculate and persist the weighted actual and plan progress of a project.
     */
    public function recalculateProjectProgress(Project $project): void
    {
        $tasks = $project->tasks;
        $totalWeight = $tasks->sum('weight');

        if ($totalWeight <= 0) {
            $project->overall_plan_progress = 0;
            $project->overall_actual_progress = 0;
        } else {
            $weightedPlan = $tasks->sum(fn (ProjectTask $task) => $this->calculateTaskPlanProgress($task) * $task->weight);
            $project->overall_plan_progress = (int) round($weightedPlan / $totalWeight);

            $weightedActual = $tasks->sum(fn (ProjectTask $task) => $task->progress_actual * $task->weight);
            $project->overall_actual_progress = (int) round($weightedActual / $totalWeight);
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
