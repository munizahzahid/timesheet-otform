<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaskDependencyResolver
{
    /**
     * Resolve effective dates for all tasks in a project.
     *
     * @return array<string, array>
     */
    public function resolve(Project $project): array
    {
        $tasks = $project->tasks()->with('predecessorTask')->get();
        $effectiveDates = [];
        $visited = [];

        foreach ($tasks as $task) {
            $this->resolveTask($task, $tasks, $effectiveDates, $visited);
        }

        return $effectiveDates;
    }

    /**
     * Resolve effective dates for a single task recursively.
     */
    protected function resolveTask(ProjectTask $task, Collection $allTasks, array &$effectiveDates, array &$visited): void
    {
        $taskId = $task->id;
        if (isset($visited[$taskId])) {
            return;
        }
        $visited[$taskId] = true;

        // Check for circular dependency
        $this->detectCycle($task, $allTasks, []);

        // Base dates: use actual if available, then revise, then plan
        $baseDates = $this->calculateBaseDates($task);

        // If task has a predecessor, calculate dependency-shifted dates
        $dependencyShift = 0;
        $effectiveDates[$taskId] = [
            'start_date' => $baseDates['start_date'],
            'end_date' => $baseDates['end_date'],
            'dependency_shift_days' => 0,
            'plan_delay_days' => $this->calculatePlanDelay($task),
            'revise_delay_days' => $this->calculateReviseDelay($task),
            'end_date_delay_days' => $this->calculateEndDateDelay($task),
        ];

        if ($task->predecessor_task_id && $task->predecessorTask) {
            $this->resolveTask($task->predecessorTask, $allTasks, $effectiveDates, $visited);

            $predecessorEffectiveEnd = $effectiveDates[$task->predecessor_task_id]['end_date'];

            if ($predecessorEffectiveEnd && $baseDates['start_date'] && $baseDates['start_date']->lte($predecessorEffectiveEnd)) {
                $dependencyShift = $predecessorEffectiveEnd->diffInDays($baseDates['start_date']) + 1;
                $effectiveDates[$taskId]['dependency_shift_days'] = $dependencyShift;
            }

            // If task has no actual dates, push effective dates after predecessor
            if (!$task->start_date_actual && !$task->end_date_actual) {
                $shiftedStart = $predecessorEffectiveEnd ? $predecessorEffectiveEnd->copy()->addDay() : $baseDates['start_date'];
                $duration = $baseDates['start_date'] && $baseDates['end_date']
                    ? $baseDates['start_date']->diffInDays($baseDates['end_date'])
                    : 0;
                $shiftedEnd = $shiftedStart->copy()->addDays($duration);

                $effectiveDates[$taskId]['start_date'] = $shiftedStart;
                $effectiveDates[$taskId]['end_date'] = $shiftedEnd;
            }
        }
    }

    /**
     * Calculate base dates for a task: actual > revise > plan.
     *
     * @return array<string, Carbon|null>
     */
    public function calculateBaseDates(ProjectTask $task): array
    {
        $startDate = $task->start_date_actual
            ?? $task->start_date_revise
            ?? $task->start_date_plan;

        $endDate = $task->end_date_actual
            ?? $task->end_date_revise
            ?? $task->end_date_plan;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Calculate plan delay: actual end date - plan end date if late.
     */
    public function calculatePlanDelay(ProjectTask $task): ?int
    {
        if (!$task->end_date_plan) {
            return null;
        }

        // If task has actual end date, compare against it
        if ($task->end_date_actual) {
            $delay = $task->end_date_plan->diffInDays($task->end_date_actual, false);
            return $delay > 0 ? $delay : 0;
        }

        // If task is incomplete and plan end has passed, compare today vs plan end
        if (!in_array($task->status, ['completed', 'cancelled']) && $task->end_date_plan->isPast()) {
            return $task->end_date_plan->diffInDays(Carbon::today(), false);
        }

        return 0;
    }

    /**
     * Calculate revise delay: actual start - revise start.
     */
    public function calculateReviseDelay(ProjectTask $task): ?int
    {
        if (!$task->start_date_revise || !$task->start_date_actual) {
            return null;
        }

        $delay = $task->start_date_revise->diffInDays($task->start_date_actual, false);
        return $delay > 0 ? $delay : 0;
    }

    /**
     * Calculate end-date delay: actual end date vs plan or revise end date.
     * Returns the number of days the actual end date exceeds the plan or revise end date.
     */
    public function calculateEndDateDelay(ProjectTask $task): ?int
    {
        // Determine the effective end date to compare against
        $effectiveEnd = $task->end_date_actual;
        $isIncomplete = !in_array($task->status, ['completed', 'cancelled']);

        // If no actual end and task is incomplete, use today as the comparison point
        if (!$effectiveEnd && $isIncomplete) {
            $effectiveEnd = Carbon::today();
        }

        if (!$effectiveEnd) {
            return null;
        }

        $delay = null;

        if ($task->end_date_plan) {
            $planDelay = $task->end_date_plan->diffInDays($effectiveEnd, false);
            if ($planDelay > 0) {
                $delay = $planDelay;
            }
        }

        // If no delay against plan (or no plan), check against revise
        if ($delay === null && $task->end_date_revise) {
            $reviseDelay = $task->end_date_revise->diffInDays($effectiveEnd, false);
            if ($reviseDelay > 0) {
                $delay = $reviseDelay;
            }
        }

        return $delay;
    }

    /**
     * Recalculate revise dates for a single task based on its predecessor.
     */
    public function recalculateTaskReviseDates(ProjectTask $task): void
    {
        if (!$task->predecessor_task_id || !$task->start_date_plan || !$task->end_date_plan) {
            return;
        }

        $predecessor = ProjectTask::find($task->predecessor_task_id);
        if (!$predecessor) {
            return;
        }

        $predecessorEffectiveEnd = $this->calculateBaseDates($predecessor)['end_date'];
        if (!$predecessorEffectiveEnd) {
            return;
        }

        $newReviseStart = $predecessorEffectiveEnd->copy()->addDay();
        $duration = $task->start_date_plan->diffInDays($task->end_date_plan);
        $newReviseEnd = $newReviseStart->copy()->addDays($duration);

        if (
            optional($task->start_date_revise)->format('Y-m-d') !== $newReviseStart->format('Y-m-d') ||
            optional($task->end_date_revise)->format('Y-m-d') !== $newReviseEnd->format('Y-m-d')
        ) {
            $task->updateQuietly([
                'start_date_revise' => $newReviseStart,
                'end_date_revise' => $newReviseEnd,
            ]);
        }
    }

    /**
     * Cascade revised dates through successor tasks after a task's dates change.
     * Updates start_date_revise and end_date_revise for all direct and indirect successors.
     */
    public function cascadeReviseDates(Project $project, ProjectTask $changedTask): void
    {
        $tasks = $project->tasks()->with('predecessorTask')->get();
        $successorsByPredecessor = $tasks->groupBy('predecessor_task_id');

        $queue = new \SplQueue();
        foreach ($successorsByPredecessor->get($changedTask->id, collect()) as $successor) {
            $queue->enqueue($successor);
        }

        while (!$queue->isEmpty()) {
            $task = $queue->dequeue();

            $predecessor = $tasks->firstWhere('id', $task->predecessor_task_id);
            if (!$predecessor) {
                continue;
            }

            // Predecessor driving end: actual > revise > plan
            $predecessorEffectiveEnd = $this->calculateBaseDates($predecessor)['end_date'];
            if (!$predecessorEffectiveEnd || !$task->start_date_plan || !$task->end_date_plan) {
                continue;
            }

            // Successor revise dates = predecessor end + 1 day, keeping original duration
            $newReviseStart = $predecessorEffectiveEnd->copy()->addDay();
            $duration = $task->start_date_plan->diffInDays($task->end_date_plan);
            $newReviseEnd = $newReviseStart->copy()->addDays($duration);

            // Only persist if dates actually changed
            if (
                optional($task->start_date_revise)->format('Y-m-d') !== $newReviseStart->format('Y-m-d') ||
                optional($task->end_date_revise)->format('Y-m-d') !== $newReviseEnd->format('Y-m-d')
            ) {
                $task->updateQuietly([
                    'start_date_revise' => $newReviseStart,
                    'end_date_revise' => $newReviseEnd,
                ]);

                // Refresh in-memory values for cascading further
                $task->start_date_revise = $newReviseStart;
                $task->end_date_revise = $newReviseEnd;

                // Also refresh the task instance in the collection so deeper cascades see the new dates
                $taskInCollection = $tasks->firstWhere('id', $task->id);
                if ($taskInCollection) {
                    $taskInCollection->start_date_revise = $newReviseStart;
                    $taskInCollection->end_date_revise = $newReviseEnd;
                }
            }

            // Enqueue successors of this task for cascading
            foreach ($successorsByPredecessor->get($task->id, collect()) as $nextSuccessor) {
                $queue->enqueue($nextSuccessor);
            }
        }
    }

    /**
     * Detect circular dependency in task chain.
     *
     * @throws \RuntimeException
     */
    public function detectCycle(ProjectTask $task, Collection $allTasks, array $chain = []): void
    {
        $taskId = $task->id;
        if (in_array($taskId, $chain)) {
            $cycle = implode(' -> ', $chain) . ' -> ' . $taskId;
            throw new \RuntimeException('Circular dependency detected: ' . $cycle);
        }

        if (!$task->predecessor_task_id) {
            return;
        }

        $predecessor = $allTasks->firstWhere('id', $task->predecessor_task_id);
        if (!$predecessor) {
            return;
        }

        $chain[] = $taskId;
        $this->detectCycle($predecessor, $allTasks, $chain);
    }

    /**
     * Validate that a task can be set as predecessor of another task.
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function validatePredecessor(ProjectTask $task, ?int $predecessorTaskId, Collection $allTasks): void
    {
        if (!$predecessorTaskId) {
            return;
        }

        if ($predecessorTaskId === $task->id) {
            throw new \InvalidArgumentException('A task cannot depend on itself.');
        }

        $predecessor = $allTasks->firstWhere('id', $predecessorTaskId);
        if (!$predecessor) {
            throw new \InvalidArgumentException('Predecessor task not found.');
        }

        // Check if the proposed predecessor is a successor of the task (would create cycle)
        $tempTask = clone $task;
        $tempTask->predecessor_task_id = $predecessorTaskId;
        $this->detectCycle($tempTask, $allTasks, []);
    }
}
