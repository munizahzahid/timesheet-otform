<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\ProjectProgressCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaskDependencyResolver
{
    private ?ProjectProgressCalculator $progressCalculator = null;

    /**
     * Resolve effective dates for all tasks in a project.
     *
     * @return array<string, array>
     */
    protected function getProgressCalculator(): ProjectProgressCalculator
    {
        if (!$this->progressCalculator) {
            $this->progressCalculator = new ProjectProgressCalculator();
        }
        return $this->progressCalculator;
    }

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
                    if ($fromSide === 'end') {
                        // ES (End-to-Start): Preserve the original gap between tasks
                        // gap = successor.plan_start - predecessor.plan_end (always non-negative for ES)
                        // successor.actual_start = predecessor.actual_end + gap
                        if ($task->predecessorTask->end_date_plan && $task->start_date_plan) {
                            $gap = (int) $task->predecessorTask->end_date_plan->diffInDays($task->start_date_plan, false);
                            $gap = max(0, $gap);
                            $newStart = $drivingDate->copy()->addDays($gap);
                        } else {
                            $newStart = $drivingDate->copy()->addDay();
                        }
                    } else {
                        // SS (Start-to-Start): Successor follows predecessor's actual start directly
                        $newStart = $drivingDate->copy();
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

        $calculator = $this->getProgressCalculator();
        $taskPlanProgress = $calculator->calculateTaskPlanProgress($task);
        $taskActualProgress = (int) $task->progress_actual;

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
            'progress_delay_days' => $calculator->calculateDelayDays($task->start_date_plan, $task->end_date_plan, $taskPlanProgress, $taskActualProgress),
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
        $logPath = storage_path('logs/cascade_actual_debug.log');
        $log = function ($line) use ($logPath) {
            file_put_contents($logPath, '[' . now()->format('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        };
        $log('=== cascadeActualStartDates ===');
        $log('Changed task id=' . $changedTask->id . ' name=' . $changedTask->task_name . ' actual_start=' . ($changedTask->start_date_actual?->format('Y-m-d') ?? 'null') . ' actual_end=' . ($changedTask->end_date_actual?->format('Y-m-d') ?? 'null'));

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

        // Also update any instances of the changed task that appear as predecessors in the collection
        // This ensures successors see the updated actual dates when checking predecessor completion
        foreach ($tasks as $task) {
            if ($task->predecessor_task_id === $changedTask->id) {
                // This task has the changed task as its predecessor
                // Ensure the predecessorTask relationship has the updated dates
                if ($task->predecessorTask && $task->predecessorTask->id === $changedTask->id) {
                    $task->predecessorTask->start_date_actual = $changedTask->start_date_actual;
                    $task->predecessorTask->end_date_actual = $changedTask->end_date_actual;
                }
            }
        }

        $queue = new \SplQueue();
        $queued = [];

        // Only enqueue successors of the changed task; do not re-evaluate the changed task itself
        foreach ($successorsByPredecessor->get($changedTask->id, collect()) as $successor) {
            $queued[$successor->id] = true;
            $queue->enqueue($successor);
        }

        while (!$queue->isEmpty()) {
            $task = $queue->dequeue();

            // Recompute actual start based on predecessor dependency

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
                $log('  isPredecessorComplete=' . ($isPredecessorComplete ? 'true' : 'false') . ' for predecessor id=' . $predecessor->id);
                if (!$isPredecessorComplete) {
                    $log('  Resetting successor because predecessor is not complete');
                    // Reset successor entirely: no actual dates, 0% progress, not started
                    $task->updateQuietly([
                        'start_date_actual' => null,
                        'end_date_actual' => null,
                        'progress_actual' => 0,
                        'status' => 'not_started',
                        'is_actual_start_manual' => false,
                    ]);
                    $task->start_date_actual = null;
                    $task->end_date_actual = null;
                    $task->progress_actual = 0;
                    $task->status = 'not_started';
                    $task->is_actual_start_manual = false;
                    $taskInCollection = $tasks->firstWhere('id', $task->id);
                    if ($taskInCollection) {
                        $taskInCollection->start_date_actual = null;
                        $taskInCollection->end_date_actual = null;
                        $taskInCollection->progress_actual = 0;
                        $taskInCollection->status = 'not_started';
                        $taskInCollection->is_actual_start_manual = false;
                    }
                    // Also update the predecessor in the collection so other successors see the reset
                    $predecessorInCollection = $tasks->firstWhere('id', $predecessor->id);
                    if ($predecessorInCollection) {
                        $predecessorInCollection->start_date_actual = null;
                        $predecessorInCollection->end_date_actual = null;
                    }
                    // Update all tasks that have this predecessor to see the reset
                    foreach ($tasks as $t) {
                        if ($t->predecessor_task_id === $predecessor->id && $t->predecessorTask && $t->predecessorTask->id === $predecessor->id) {
                            $t->predecessorTask->start_date_actual = null;
                            $t->predecessorTask->end_date_actual = null;
                        }
                    }
                    // Still enqueue successors of this task for cascading (don't skip)
                    // This ensures indirect successors are also reset
                    foreach ($successorsByPredecessor->get($task->id, collect()) as $nextSuccessor) {
                        if (!isset($queued[$nextSuccessor->id])) {
                            $queued[$nextSuccessor->id] = true;
                            $queue->enqueue($nextSuccessor);
                        }
                    }
                    continue;
                }

                // Predecessor is complete - compute successor actual_start
                    $predecessorDates = $this->getLaneBaseDates($predecessor, 'actual');
                    $drivingDate = $fromSide === 'end' ? $predecessorDates['end_date'] : $predecessorDates['start_date'];

                    $log('Processing successor id=' . $task->id . ' name=' . $task->task_name);
                    $log('  dependency_type=' . $task->dependency_type . ' fromSide=' . $fromSide);
                    $log('  predecessor id=' . $predecessor->id . ' actual_start=' . ($predecessor->start_date_actual?->format('Y-m-d') ?? 'null') . ' actual_end=' . ($predecessor->end_date_actual?->format('Y-m-d') ?? 'null') . ' plan_start=' . ($predecessor->start_date_plan?->format('Y-m-d') ?? 'null') . ' plan_end=' . ($predecessor->end_date_plan?->format('Y-m-d') ?? 'null'));
                    $log('  successor current actual_start=' . ($task->start_date_actual?->format('Y-m-d') ?? 'null') . ' actual_end=' . ($task->end_date_actual?->format('Y-m-d') ?? 'null') . ' plan_start=' . ($task->start_date_plan?->format('Y-m-d') ?? 'null'));

                    if ($drivingDate) {
                        if ($fromSide === 'end') {
                            // ES (End-to-Start): Preserve the original gap between tasks
                            // gap = successor.plan_start - predecessor.plan_end (always non-negative for ES)
                            // successor.actual_start = predecessor.actual_end + gap
                            if ($predecessor->end_date_plan && $task->start_date_plan) {
                                $gap = (int) $predecessor->end_date_plan->diffInDays($task->start_date_plan, false);
                                $gap = max(0, $gap);
                                $newStart = $drivingDate->copy()->addDays($gap);
                                $log('  ES gap=' . $gap . ' drivingDate=' . $drivingDate->format('Y-m-d') . ' => newStart=' . $newStart->format('Y-m-d'));
                            } else {
                                $newStart = $drivingDate->copy()->addDay();
                                $log('  ES fallback +1 day: drivingDate=' . $drivingDate->format('Y-m-d') . ' => newStart=' . $newStart->format('Y-m-d'));
                            }
                        } else {
                            // SS (Start-to-Start): Successor follows predecessor's actual start directly
                            $newStart = $drivingDate->copy();
                            $log('  SS: drivingDate=' . $drivingDate->format('Y-m-d') . ' => newStart=' . $newStart->format('Y-m-d'));
                        }

                        $currentStart = $task->start_date_actual;
                        $currentEnd = $task->end_date_actual;
                        $hasEnd = $currentStart && $currentEnd;
                        $duration = $hasEnd ? $currentStart->diffInDays($currentEnd) : null;

                        // Always update when predecessor completes (ignore is_actual_start_manual flag)
                        // This ensures successors follow the predecessor's actual end even if they had manual dates
                        if (!$currentStart || $currentStart->format('Y-m-d') !== $newStart->format('Y-m-d')) {
                            $updateData = ['start_date_actual' => $newStart];
                            if ($hasEnd) {
                                $updateData['end_date_actual'] = $newStart->copy()->addDays($duration);
                            }
                            $log('  Updating successor with: ' . json_encode($updateData));
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
                        } else {
                            $log('  No update needed, current start already ' . $newStart->format('Y-m-d'));
                        }
                    } else {
                        $log('  No driving date for predecessor');
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

    /**
     * Validate a proposed date value for a task field.
     *
     * @return array<string, string> Array of field => error message
     */
    public function validateTaskDate(ProjectTask $task, string $field, mixed $value, ?ProjectTask $predecessor = null): array
    {
        $errors = [];

        if (empty($value)) {
            return $errors;
        }

        try {
            $newDate = Carbon::parse($value)->startOfDay();
        } catch (\Exception $e) {
            $errors[$field] = 'Invalid date format.';
            return $errors;
        }

        // End date must not be before start date of the same lane
        if (str_starts_with($field, 'end_date_')) {
            $lane = str_replace('end_date_', '', $field); // plan, revise, actual
            $startField = "start_date_{$lane}";
            $startDate = $task->{$startField};

            // If we are also changing the start date in the same request, it may not be persisted yet.
            // The caller should set the relevant start date on the task first for accurate validation.
            if ($startDate && $newDate->lt($startDate->copy()->startOfDay())) {
                $errors[$field] = 'End date cannot be before the ' . ucfirst($lane) . ' Start date.';
            }
        }

        // Validate start dates against predecessor dependency
        if (str_starts_with($field, 'start_date_') && $predecessor) {
            $lane = str_replace('start_date_', '', $field);
            $fromSide = $this->parseDependencyType($task->dependency_type)['from_side'];

            // Use the appropriate lane of the predecessor (do not mix Actual into Plan/Revise)
            $predecessorDate = $this->getPredecessorDrivingDateForLane($predecessor, $lane, $fromSide);

            if ($fromSide === 'end') {
                if (!$predecessorDate) {
                    // Actual lane requires predecessor to have an Actual End
                    if ($lane === 'actual') {
                        $errors[$field] = 'Predecessor does not have an Actual End yet.';
                    }
                    // For Plan/Revise, no constraint exists until predecessor has an End date
                } elseif ($newDate->lte($predecessorDate)) {
                    $errors[$field] = 'Start date must be at least 1 day after the predecessor\'s End date.';
                }
            } else {
                if (!$predecessorDate) {
                    // Actual lane requires predecessor to have an Actual Start
                    if ($lane === 'actual') {
                        $errors[$field] = 'Predecessor does not have an Actual Start yet.';
                    }
                    // For Plan/Revise, no constraint exists until predecessor has a Start date
                } elseif ($newDate->lt($predecessorDate)) {
                    $errors[$field] = 'Start date cannot be earlier than the predecessor\'s Start date.';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a set of proposed dates for a task.
     *
     * @param array<string, string|null> $dates Array of field => value
     * @return array<string, string> Array of field => error message
     */
    public function validateTaskDates(ProjectTask $task, array $dates, ?ProjectTask $predecessor = null): array
    {
        $errors = [];

        // Build a temporary task with the proposed dates for cross-field validation
        $tempTask = clone $task;
        foreach ($dates as $field => $value) {
            if (!empty($value) && str_contains($field, '_date_')) {
                try {
                    $tempTask->{$field} = Carbon::parse($value)->startOfDay();
                } catch (\Exception $e) {
                    $errors[$field] = 'Invalid date format.';
                }
            }
        }

        foreach ($dates as $field => $value) {
            if (isset($errors[$field])) {
                continue;
            }
            $fieldErrors = $this->validateTaskDate($tempTask, $field, $value, $predecessor);
            $errors = array_merge($errors, $fieldErrors);
        }

        return $errors;
    }

    /**
     * Get the driving date from the predecessor for a specific lane and side.
     *
     * @return Carbon|null
     */
    protected function getPredecessorDrivingDateForLane(ProjectTask $predecessor, string $lane, string $fromSide): ?Carbon
    {
        if ($lane === 'plan') {
            $dateField = $fromSide === 'end' ? 'end_date_plan' : 'start_date_plan';
            return $predecessor->{$dateField};
        }

        if ($lane === 'revise') {
            $dateField = $fromSide === 'end' ? 'end_date_revise' : 'start_date_revise';
            $fallbackField = $fromSide === 'end' ? 'end_date_plan' : 'start_date_plan';
            return $predecessor->{$dateField} ?? $predecessor->{$fallbackField};
        }

        if ($lane === 'actual') {
            $dateField = $fromSide === 'end' ? 'end_date_actual' : 'start_date_actual';
            return $predecessor->{$dateField};
        }

        return null;
    }

    /**
     * Validate plan date constraints against parent phase and project.
     * Plan dates must be within the parent phase's plan dates (if has parent)
     * or within the project's plan dates (if no parent).
     *
     * @param ProjectTask|ProjectPhase $entity The task or phase being validated
     * @param array<string, string|null> $dates Array of field => value
     * @param Project $project The project
     * @return array<string, string> Array of field => error message
     */
    public function validatePlanDateConstraints($entity, array $dates, Project $project): array
    {
        $errors = [];

        // Only validate plan dates
        $planFields = ['start_date_plan', 'end_date_plan'];
        $proposedPlanDates = array_intersect_key($dates, array_flip($planFields));

        if (empty($proposedPlanDates)) {
            return $errors;
        }

        // Determine the parent (phase) or project constraints
        $parentPhase = null;
        if ($entity instanceof ProjectTask && $entity->phase_id) {
            $parentPhase = $project->phases()->where('id', $entity->phase_id)->first();
        }

        if ($parentPhase) {
            // Subtask: must be within parent phase's plan dates
            $minStart = $parentPhase->start_date_plan;
            $maxEnd = $parentPhase->end_date_plan;
            $parentName = $parentPhase->phase_name;
        } else {
            // Phase/Task: must be within project's plan dates
            $minStart = $project->start_date_plan;
            $maxEnd = $project->end_date_plan;
            $parentName = $project->project_name;
        }

        // Validate start_date_plan
        if (isset($proposedPlanDates['start_date_plan']) && $minStart) {
            try {
                $newStart = Carbon::parse($proposedPlanDates['start_date_plan'])->startOfDay();
                if ($newStart->lt($minStart->startOfDay())) {
                    $errors['start_date_plan'] = "Start date cannot be earlier than {$parentName}'s plan start ({$minStart->format('Y-m-d')}).";
                }
            } catch (\Exception $e) {
                $errors['start_date_plan'] = 'Invalid date format.';
            }
        }

        // Validate end_date_plan
        if (isset($proposedPlanDates['end_date_plan']) && $maxEnd) {
            try {
                $newEnd = Carbon::parse($proposedPlanDates['end_date_plan'])->startOfDay();
                if ($newEnd->gt($maxEnd->startOfDay())) {
                    $errors['end_date_plan'] = "End date cannot be later than {$parentName}'s plan end ({$maxEnd->format('Y-m-d')}).";
                }
            } catch (\Exception $e) {
                $errors['end_date_plan'] = 'Invalid date format.';
            }
        }

        return $errors;
    }
}
