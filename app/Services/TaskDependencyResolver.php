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

        $planStart = $task->start_date_plan;
        $planEnd = $task->end_date_plan;
        $actualStart = $task->start_date_actual;
        $actualEnd = $task->end_date_actual;
        $dependencyShift = 0;

        if ($task->predecessor_task_id && $task->predecessorTask) {
            $this->resolveTask($task->predecessorTask, $allTasks, $effectiveDates, $visited);

            $fromSide = $this->parseDependencyType($task->dependency_type)['from_side'];

            $predecessorPlanDates = $this->getLaneBaseDates($task->predecessorTask, 'plan');
            $predecessorActualDates = $this->getLaneBaseDates($task->predecessorTask, 'actual');

            // Resolve plan lane
            if ($planStart) {
                $drivingDate = $fromSide === 'end' ? $predecessorPlanDates['end_date'] : $predecessorPlanDates['start_date'];
                if ($drivingDate) {
                    if ($fromSide === 'end' && $planStart->lte($drivingDate)) {
                        $planStart = $drivingDate->copy()->addDay();
                    } elseif ($fromSide === 'start' && $planStart->lt($drivingDate)) {
                        $planStart = $drivingDate->copy();
                    }

                    if ($planStart && $task->start_date_plan && $task->end_date_plan) {
                        $duration = $task->start_date_plan->diffInDays($task->end_date_plan);
                        $planEnd = $planStart->copy()->addDays($duration);
                    }
                }
            }

            // Resolve actual lane (skip shifting if the user has manually locked the actual start)
            // Only resolve actual dates if predecessor is complete (has required date based on dependency type)
            // Check actual database value, not provisional date from getLaneBaseDates
            $isPredecessorComplete = $fromSide === 'end'
                ? !empty($task->predecessorTask->end_date_actual)
                : !empty($task->predecessorTask->start_date_actual);

            if ($isPredecessorComplete) {
                $drivingDate = $fromSide === 'end' ? $predecessorActualDates['end_date'] : $predecessorActualDates['start_date'];
                if ($drivingDate) {
                    // Calculate predecessor's offset from plan and apply to successor's plan start
                    $offset = $this->calculatePredecessorOffset($task->predecessorTask, $fromSide);
                    if ($offset !== null && $task->start_date_plan) {
                        $newStart = $task->start_date_plan->copy()->addDays($offset);
                    } else {
                        $newStart = $drivingDate->copy();
                    }

                    // Apply dependency constraint (successor can't start before predecessor's actual date)
                    if ($fromSide === 'end') {
                        $minStart = $drivingDate->copy()->addDay();
                        if ($newStart->lt($minStart)) {
                            $newStart = $minStart;
                        }
                    } else {
                        if ($newStart->lt($drivingDate)) {
                            $newStart = $drivingDate->copy();
                        }
                    }

                    if (!$actualStart || $actualStart->format('Y-m-d') !== $newStart->format('Y-m-d')) {
                        $actualStart = $newStart;
                        if ($actualStart && $task->start_date_actual && $task->end_date_actual) {
                            $duration = $task->start_date_actual->diffInDays($task->end_date_actual);
                            $actualEnd = $actualStart->copy()->addDays($duration);
                        }
                    }
                }
            }

            // Dependency shift based on the effective lane (actual if present, otherwise plan)
            $baseStart = $task->start_date_actual ?? $task->start_date_plan;
            $resolvedStart = $actualStart ?? $planStart;
            if ($baseStart && $resolvedStart) {
                $dependencyShift = $resolvedStart->diffInDays($baseStart);
            }
        }

        // Effective fallback: actual > plan (revise is manual and never cascades)
        $effectiveStart = $actualStart ?? $planStart;
        $effectiveEnd = $actualEnd ?? $planEnd;

        $effectiveDates[$taskId] = [
            'start_date' => $effectiveStart,
            'end_date' => $effectiveEnd,
            'plan_start_date' => $planStart,
            'plan_end_date' => $planEnd,
            'actual_start_date' => $actualStart,
            'actual_end_date' => $actualEnd,
            'dependency_shift_days' => $dependencyShift,
            'plan_delay_days' => $this->calculatePlanDelay($task),
            'revise_delay_days' => $this->calculateReviseDelay($task),
            'end_date_delay_days' => $this->calculateEndDateDelay($task),
        ];
    }

    /**
     * Parse dependency type into the driving source side (start or end).
     * Supports simple logical types: end_to_start, start_to_start.
     * Legacy lane-prefixed types are also accepted and normalized.
     */
    protected function parseDependencyType(?string $type): array
    {
        $type = $type ?? 'end_to_start';

        if (str_contains($type, 'start_to_start')) {
            return ['from_side' => 'start'];
        }

        return ['from_side' => 'end'];
    }

    /**
     * Calculate the offset in days between predecessor's plan and actual dates.
     * Returns negative if actual is earlier than plan, positive if actual is later.
     */
    protected function calculatePredecessorOffset(ProjectTask $predecessor, string $fromSide): ?int
    {
        if ($fromSide === 'end') {
            $planDate = $predecessor->end_date_plan;
            $actualDate = $predecessor->end_date_actual;
        } else {
            $planDate = $predecessor->start_date_plan;
            $actualDate = $predecessor->start_date_actual;
        }

        if (!$planDate || !$actualDate) {
            return null;
        }

        return $planDate->diffInDays($actualDate, false);
    }

    /**
     * Get the raw start/end dates for a specific date lane (plan or actual).
     */
    protected function getLaneBaseDates(ProjectTask $task, string $lane): array
    {
        if ($lane === 'actual') {
            $endDate = $task->end_date_actual;
            // For ongoing actual tasks without an end date, use today as a provisional end
            if ($task->start_date_actual && !$endDate) {
                $endDate = Carbon::today();
            }
            return [
                'start_date' => $task->start_date_actual,
                'end_date' => $endDate,
            ];
        }

        return [
            'start_date' => $task->start_date_plan,
            'end_date' => $task->end_date_plan,
        ];
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

        $predecessorBaseDates = $this->calculateBaseDates($predecessor);
        $dependencyType = $task->dependency_type ?? 'end_to_start';
        $predecessorDrivingDate = $dependencyType === 'start_to_start'
            ? $predecessorBaseDates['start_date']
            : $predecessorBaseDates['end_date'];

        if (!$predecessorDrivingDate) {
            return;
        }

        $newReviseStart = $dependencyType === 'start_to_start'
            ? $predecessorDrivingDate->copy()
            : $predecessorDrivingDate->copy()->addDay();
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

            // Predecessor driving date: actual > revise > plan
            $predecessorBaseDates = $this->calculateBaseDates($predecessor);
            $dependencyType = $task->dependency_type ?? 'end_to_start';
            $predecessorDrivingDate = $dependencyType === 'start_to_start'
                ? $predecessorBaseDates['start_date']
                : $predecessorBaseDates['end_date'];
            if (!$predecessorDrivingDate || !$task->start_date_plan || !$task->end_date_plan) {
                continue;
            }

            // Successor revise dates = predecessor driving date (or +1 day for end_to_start), keeping original duration
            $newReviseStart = $dependencyType === 'start_to_start'
                ? $predecessorDrivingDate->copy()
                : $predecessorDrivingDate->copy()->addDay();
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
     * Cascade actual start dates through successor tasks after a task's actual dates change.
     * Only writes to successors where is_actual_start_manual is false.
     */
    public function cascadeActualStartDates(Project $project, ProjectTask $changedTask): void
    {
        $tasks = $project->tasks()->with('predecessorTask')->get();
        $successorsByPredecessor = $tasks->groupBy('predecessor_task_id');

        // Use fresh in-memory values for the changed task in case it has just been updated
        $changedInCollection = $tasks->firstWhere('id', $changedTask->id);
        if ($changedInCollection) {
            $changedInCollection->start_date_actual = $changedTask->start_date_actual;
            $changedInCollection->end_date_actual = $changedTask->end_date_actual;
            $changedInCollection->predecessor_task_id = $changedTask->predecessor_task_id;
            $changedInCollection->dependency_type = $changedTask->dependency_type;
        }

        $queue = new \SplQueue();
        $queue->enqueue($changedTask);
        $queued = [$changedTask->id => true];

        while (!$queue->isEmpty()) {
            $task = $queue->dequeue();

            // Always recompute actual start based on predecessor dependency

            $predecessor = $tasks->firstWhere('id', $task->predecessor_task_id);
            if ($predecessor) {
                $fromSide = $this->parseDependencyType($task->dependency_type)['from_side'];

                // Check if predecessor is complete based on dependency type
                // For end_to_start: predecessor must have end_date_actual
                // For start_to_start: predecessor must have start_date_actual
                $isPredecessorComplete = $fromSide === 'end'
                    ? !empty($predecessor->end_date_actual)
                    : !empty($predecessor->start_date_actual);

                // Only set successor actual_start if predecessor is complete
                if (!$isPredecessorComplete) {
                    // Ensure actual start is null if the predecessor is not yet complete
                    $task->updateQuietly(['start_date_actual' => null]);
                    $task->start_date_actual = null;
                    $taskInCollection = $tasks->firstWhere('id', $task->id);
                    if ($taskInCollection) {
                        $taskInCollection->start_date_actual = null;
                    }
                    continue;
                }

                // Predecessor is complete - compute successor actual_start
                    $predecessorDates = $this->getLaneBaseDates($predecessor, 'actual');
                    $drivingDate = $fromSide === 'end' ? $predecessorDates['end_date'] : $predecessorDates['start_date'];

                    if ($drivingDate) {
                        // Calculate predecessor's offset from plan and apply to successor's plan start
                        $offset = $this->calculatePredecessorOffset($predecessor, $fromSide);
                        if ($offset !== null && $task->start_date_plan) {
                            $newStart = $task->start_date_plan->copy()->addDays($offset);
                        } else {
                            $newStart = $drivingDate->copy();
                        }

                        // Apply dependency constraint (successor can't start before predecessor's actual date)
                        if ($fromSide === 'end') {
                            $minStart = $drivingDate->copy()->addDay();
                            if ($newStart->lt($minStart)) {
                                $newStart = $minStart;
                            }
                        } else {
                            if ($newStart->lt($drivingDate)) {
                                $newStart = $drivingDate->copy();
                            }
                        }

                        $currentStart = $task->start_date_actual;
                        $currentEnd = $task->end_date_actual;
                        $hasEnd = $currentStart && $currentEnd;
                        $duration = $hasEnd ? $currentStart->diffInDays($currentEnd) : null;

                        if (!$currentStart || $currentStart->format('Y-m-d') !== $newStart->format('Y-m-d')) {
                            $updateData = ['start_date_actual' => $newStart];
                            if ($hasEnd) {
                                $updateData['end_date_actual'] = $newStart->copy()->addDays($duration);
                            }
                            $task->updateQuietly($updateData);
                            $task->start_date_actual = $newStart;
                            if ($hasEnd) {
                                $task->end_date_actual = $newStart->copy()->addDays($duration);
                            }

                            // Refresh the task instance in the collection so deeper cascades see the new dates
                            $taskInCollection = $tasks->firstWhere('id', $task->id);
                            if ($taskInCollection) {
                                $taskInCollection->start_date_actual = $newStart;
                                if ($hasEnd) {
                                    $taskInCollection->end_date_actual = $newStart->copy()->addDays($duration);
                                }
                            }
                        }
                    }
                }

            // Enqueue successors of this task for cascading
            foreach ($successorsByPredecessor->get($task->id, collect()) as $nextSuccessor) {
                if (!isset($queued[$nextSuccessor->id])) {
                    $queued[$nextSuccessor->id] = true;
                    $queue->enqueue($nextSuccessor);
                }
            }
        }
    }

    /**
     * Cascade plan start/end dates through successor tasks after a task's plan dates change.
     * The successor's plan start shifts by the same number of days the predecessor's driving
     * plan date changed, keeping the original duration intact.
     */
    public function cascadePlanDates(Project $project, ProjectTask $changedTask, ?Carbon $oldPlanStart, ?Carbon $oldPlanEnd): void
    {
        // Only run if there is a real plan-date change to propagate
        $changedInCollection = $project->tasks()->with('predecessorTask')->get()->firstWhere('id', $changedTask->id);
        if (!$changedInCollection) {
            return;
        }

        $newPlanStart = $changedInCollection->start_date_plan;
        $newPlanEnd = $changedInCollection->end_date_plan;
        if (
            optional($oldPlanStart)->format('Y-m-d') === optional($newPlanStart)->format('Y-m-d') &&
            optional($oldPlanEnd)->format('Y-m-d') === optional($newPlanEnd)->format('Y-m-d')
        ) {
            return;
        }

        $tasks = $project->tasks()->with('predecessorTask')->get();
        $successorsByPredecessor = $tasks->groupBy('predecessor_task_id');

        $originalDates = [
            $changedTask->id => [
                'start_date' => $oldPlanStart,
                'end_date' => $oldPlanEnd,
            ],
        ];

        $queue = new \SplQueue();
        $queued = [];
        foreach ($successorsByPredecessor->get($changedTask->id, collect()) as $successor) {
            $queued[$successor->id] = true;
            $queue->enqueue($successor);
        }

        while (!$queue->isEmpty()) {
            $task = $queue->dequeue();

            if (!isset($originalDates[$task->id])) {
                $originalDates[$task->id] = [
                    'start_date' => $task->start_date_plan,
                    'end_date' => $task->end_date_plan,
                ];
            }

            if (!$task->start_date_plan || !$task->end_date_plan) {
                continue;
            }

            $predecessor = $tasks->firstWhere('id', $task->predecessor_task_id);
            if (!$predecessor || !isset($originalDates[$predecessor->id])) {
                continue;
            }

            $fromSide = $this->parseDependencyType($task->dependency_type)['from_side'];
            $predecessorOriginal = $originalDates[$predecessor->id];
            $oldDriving = $fromSide === 'end' ? $predecessorOriginal['end_date'] : $predecessorOriginal['start_date'];
            $newDriving = $fromSide === 'end' ? $predecessor->end_date_plan : $predecessor->start_date_plan;

            if (!$oldDriving || !$newDriving || $oldDriving->format('Y-m-d') === $newDriving->format('Y-m-d')) {
                continue;
            }

            $delta = $newDriving->greaterThan($oldDriving)
                ? $oldDriving->diffInDays($newDriving)
                : -$newDriving->diffInDays($oldDriving);

            $newStart = $task->start_date_plan->copy()->addDays($delta);
            $duration = $task->start_date_plan->diffInDays($task->end_date_plan);
            $newEnd = $newStart->copy()->addDays($duration);

            if (
                optional($task->start_date_plan)->format('Y-m-d') !== $newStart->format('Y-m-d') ||
                optional($task->end_date_plan)->format('Y-m-d') !== $newEnd->format('Y-m-d')
            ) {
                $task->updateQuietly([
                    'start_date_plan' => $newStart,
                    'end_date_plan' => $newEnd,
                ]);
                $task->start_date_plan = $newStart;
                $task->end_date_plan = $newEnd;

                $taskInCollection = $tasks->firstWhere('id', $task->id);
                if ($taskInCollection) {
                    $taskInCollection->start_date_plan = $newStart;
                    $taskInCollection->end_date_plan = $newEnd;
                }
            }

            foreach ($successorsByPredecessor->get($task->id, collect()) as $nextSuccessor) {
                if (!isset($queued[$nextSuccessor->id])) {
                    $queued[$nextSuccessor->id] = true;
                    $queue->enqueue($nextSuccessor);
                }
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
