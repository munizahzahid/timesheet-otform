<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\GanttChangeLog;
use App\Models\ProjectProgressLog;
use App\Models\ProjectTask;
use App\Services\GanttChangeLogger;
use App\Services\GanttExcelExport;
use App\Services\ProjectProgressCalculator;
use App\Services\TaskDependencyResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectTaskController extends Controller
{
    /**
     * List tasks for a project
     */
    public function index(Project $project)
    {
        return redirect()->to(route('admin.project.projects.show', $project) . '?tab=tasks');
    }

    /**
     * Show create task form
     */
    public function create(Project $project, Request $request)
    {
        $phases = $project->phases()->orderBy('phase_order')->get();
        $tasks = $project->tasks()->orderBy('task_order')->get();
        $users = \App\Models\User::where('is_active', true)->get();
        $defaultPhaseId = $request->query('phase_id');
        return view('admin.project.tasks.create', compact('project', 'phases', 'tasks', 'users', 'defaultPhaseId'));
    }

    /**
     * Store a new task
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'phase_id' => 'nullable|exists:project_phases,id',
            'task_name' => 'required|string|max:255',
            'task_order' => 'required|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'progress_actual' => 'nullable|integer|min:0|max:100',
            'weight' => 'required|integer|min:0|max:100',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
            'status' => 'nullable|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'remarks' => 'nullable|string',
            'predecessor_task_id' => 'nullable|integer|exists:project_tasks,id',
            'dependency_type' => 'required_with:predecessor_task_id|string|in:end_to_start,start_to_start',
        ]);

        $validated['project_id'] = $project->id;
        $validated['progress_actual'] = $validated['progress_actual'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['is_actual_start_manual'] = !empty($validated['start_date_actual']);

        $this->validateTaskWeightSum($project, $validated['phase_id'] ?? null, $validated['weight'], null);

        $validated['task_order'] = $this->reorderTasksForOrder(
            $project,
            null,
            null,
            $validated['phase_id'] ?? null,
            (int) $validated['task_order']
        );

        $resolver = new TaskDependencyResolver();
        if (!empty($validated['predecessor_task_id'])) {
            $tempTask = new ProjectTask($validated);
            try {
                $resolver->validatePredecessor($tempTask, $validated['predecessor_task_id'], $project->tasks);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        $task = ProjectTask::create($validated);

        GanttChangeLogger::log(
            $project,
            auth()->user(),
            'task_create',
            $task,
            null,
            null,
            null,
            null,
            "Task '{$task->task_name}' created"
        );

        $resolver->cascadeActualStartDates($project, $task);
        (new ProjectProgressCalculator())->recalculateFromTask($task);

        $query = ['tab' => $request->input('tab', 'tasks')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('admin.project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Task created successfully.');
    }

    /**
     * Show task details
     */
    public function show(Project $project, ProjectTask $task)
    {
        $task->load(['phase', 'assignedTo', 'predecessorTask']);

        $resolver = new TaskDependencyResolver();
        $effectiveDates = $resolver->resolve($project);
        $effective = $effectiveDates[$task->id] ?? [];

        return view('admin.project.tasks.show', compact('project', 'task', 'effective'));
    }

    /**
     * Show edit task form
     */
    public function edit(Project $project, ProjectTask $task)
    {
        $phases = $project->phases()->orderBy('phase_order')->get();
        $tasks = $project->tasks()->where('id', '!=', $task->id)->orderBy('task_order')->get();
        $users = \App\Models\User::where('is_active', true)->get();
        return view('admin.project.tasks.edit', compact('project', 'task', 'phases', 'tasks', 'users'));
    }

    /**
     * Update task
     */
    public function update(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'phase_id' => 'nullable|exists:project_phases,id',
            'task_name' => 'required|string|max:255',
            'task_order' => 'required|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'progress_actual' => 'nullable|integer|min:0|max:100',
            'weight' => 'required|integer|min:0|max:100',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
            'status' => 'nullable|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'remarks' => 'nullable|string',
            'predecessor_task_id' => 'nullable|integer|exists:project_tasks,id|not_in:' . $task->id,
            'dependency_type' => 'required_with:predecessor_task_id|string|in:end_to_start,start_to_start',
        ]);

        $validated['progress_actual'] = $validated['progress_actual'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['is_actual_start_manual'] = !empty($validated['start_date_actual']);

        $this->validateTaskWeightSum($project, $validated['phase_id'] ?? null, $validated['weight'], $task->id);

        $resolver = new TaskDependencyResolver();
        if (!empty($validated['predecessor_task_id'])) {
            $tempTask = clone $task;
            $tempTask->predecessor_task_id = $validated['predecessor_task_id'];
            $tempTask->dependency_type = $validated['dependency_type'] ?? 'end_to_start';
            try {
                $resolver->validatePredecessor($tempTask, $validated['predecessor_task_id'], $project->tasks);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        $oldPlanStart = $task->start_date_plan ? $task->start_date_plan->copy() : null;
        $oldPlanEnd = $task->end_date_plan ? $task->end_date_plan->copy() : null;
        $oldActualEnd = $task->end_date_actual ? $task->end_date_actual->format('Y-m-d') : null;
        $oldReviseEnd = $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : null;
        $oldPhaseId = $task->phase_id;
        $oldOrder = $task->task_order;

        $validated['task_order'] = $this->reorderTasksForOrder(
            $project,
            $oldPhaseId,
            $oldOrder,
            $validated['phase_id'] ?? null,
            (int) $validated['task_order']
        );

        $task->update($validated);

        // Propagate plan date changes to successor tasks, then recalculate actual starts
        $resolver->cascadePlanDates($project, $task, $oldPlanStart, $oldPlanEnd);

        $task->refresh();
        $resolver->cascadeActualStartDates($project, $task);

        // Recalculate progress for affected phase(s) and project
        $calculator = new ProjectProgressCalculator();
        if ($oldPhaseId && $oldPhaseId != ($validated['phase_id'] ?? null)) {
            $oldPhase = ProjectPhase::find($oldPhaseId);
            if ($oldPhase) {
                $calculator->recalculatePhaseProgress($oldPhase);
            }
        }
        $calculator->recalculateFromTask($task->refresh());

        $fieldsToLog = [
            'task_name' => 'Task name',
            'phase_id' => 'Phase',
            'assigned_to' => 'Assigned to',
            'weight' => 'Weight',
            'start_date_plan' => 'Plan start',
            'end_date_plan' => 'Plan end',
            'start_date_revise' => 'Revise start',
            'end_date_revise' => 'Revise end',
            'start_date_actual' => 'Actual start',
            'end_date_actual' => 'Actual end',
            'progress_actual' => 'Progress',
            'status' => 'Status',
        ];
        foreach ($fieldsToLog as $field => $label) {
            if (array_key_exists($field, $validated)) {
                $oldValue = $task->getOriginal($field);
                $newValue = $validated[$field];
                if ($oldValue != $newValue) {
                    GanttChangeLogger::log(
                        $project,
                        auth()->user(),
                        'task_update',
                        $task,
                        null,
                        $label,
                        $oldValue,
                        $newValue
                    );
                }
            }
        }

        $query = ['tab' => $request->input('tab', 'tasks')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('admin.project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Quick update task progress and status
     */
    public function quickUpdate(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'progress_actual' => 'required|integer|min:0|max:100',
            'status' => 'required|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldValues = [
            'progress_actual' => $task->progress_actual,
            'status' => $task->status,
            'start_date_actual' => $task->start_date_actual ? $task->start_date_actual->format('Y-m-d') : null,
            'end_date_actual' => $task->end_date_actual ? $task->end_date_actual->format('Y-m-d') : null,
            'start_date_revise' => $task->start_date_revise ? $task->start_date_revise->format('Y-m-d') : null,
            'end_date_revise' => $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : null,
        ];

        $updateData = [
            'progress_actual' => $validated['progress_actual'],
            'status' => $validated['status'],
            'start_date_actual' => $validated['start_date_actual'],
            'end_date_actual' => $validated['end_date_actual'],
            'start_date_revise' => $validated['start_date_revise'],
            'end_date_revise' => $validated['end_date_revise'],
            'is_actual_start_manual' => !empty($validated['start_date_actual']),
        ];

        $task->update($updateData);

        $resolver = new TaskDependencyResolver();
        $task->refresh();
        $resolver->cascadeActualStartDates($project, $task);

        // Recalculate progress when progress changes
        if ($oldValues['progress_actual'] !== $validated['progress_actual']) {
            (new ProjectProgressCalculator())->recalculateFromTask($task->refresh());
        }

        // Log changes for each field
        $fieldsToLog = ['progress_actual', 'status', 'start_date_actual', 'end_date_actual', 'start_date_revise', 'end_date_revise'];
        foreach ($fieldsToLog as $field) {
            $newValue = $validated[$field] ?? null;
            if ($oldValues[$field] !== $newValue && ($oldValues[$field] !== null || $newValue !== null)) {
                ProjectProgressLog::create([
                    'project_id' => $project->id,
                    'phase_id' => $task->phase_id,
                    'task_id' => $task->id,
                    'log_type' => 'update',
                    'field_name' => $field,
                    'old_value' => $oldValues[$field],
                    'new_value' => $newValue,
                    'changed_by' => auth()->id(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                $fieldLabels = [
                    'progress_actual' => 'Progress',
                    'status' => 'Status',
                    'start_date_actual' => 'Actual start',
                    'end_date_actual' => 'Actual end',
                    'start_date_revise' => 'Revise start',
                    'end_date_revise' => 'Revise end',
                ];
                GanttChangeLogger::log(
                    $project,
                    auth()->user(),
                    $field === 'progress_actual' ? 'progress_update' : 'task_update',
                    $task,
                    null,
                    $fieldLabels[$field] ?? $field,
                    $oldValues[$field],
                    $newValue
                );
            }
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    /**
     * Inline update task fields (dates, progress, status, weight, end_date_revise)
     * Called via AJAX from the Gantt and Kanban boards
     */
    public function inlineUpdate(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'weight' => 'sometimes|nullable|integer|min:0|max:100',
            'end_date_revise' => 'sometimes|nullable|date',
            'start_date_plan' => 'sometimes|nullable|date',
            'end_date_plan' => 'sometimes|nullable|date',
            'start_date_revise' => 'sometimes|nullable|date',
            'start_date_actual' => 'sometimes|nullable|date',
            'end_date_actual' => 'sometimes|nullable|date',
            'progress_actual' => 'sometimes|nullable|integer|min:0|max:100',
            'task_order' => 'sometimes|required|integer|min:1',
        ]);

        if (array_key_exists('weight', $validated)) {
            try {
                $this->validateTaskWeightSum($project, $task->phase_id, $validated['weight'], $task->id);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exceed weight percentage. ' . collect($e->errors())->flatten()->first(),
                ], 422);
            }
        }

        $oldPlanStart = $task->start_date_plan ? $task->start_date_plan->copy() : null;
        $oldPlanEnd = $task->end_date_plan ? $task->end_date_plan->copy() : null;

        $dateFields = [
            'status', 'weight', 'end_date_revise',
            'start_date_plan', 'end_date_plan', 'start_date_revise',
            'start_date_actual', 'end_date_actual', 'progress_actual',
        ];
        $updateData = [];
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if (array_key_exists('start_date_actual', $validated) && !empty($validated['start_date_actual'])) {
            $updateData['is_actual_start_manual'] = true;
        }

        if (array_key_exists('task_order', $validated)) {
            $newOrder = $this->reorderTasksForOrder(
                $project,
                $task->phase_id,
                $task->task_order,
                $task->phase_id,
                (int) $validated['task_order']
            );
            $updateData['task_order'] = $newOrder;
        }

        $oldValues = $task->getOriginal();

        if (!empty($updateData)) {
            $task->update($updateData);
        }

        $task->refresh();

        $resolver = new TaskDependencyResolver();

        if (array_key_exists('start_date_plan', $updateData) || array_key_exists('end_date_plan', $updateData)) {
            $resolver->cascadePlanDates($project, $task, $oldPlanStart, $oldPlanEnd);
        }

        if (array_key_exists('start_date_actual', $updateData)) {
            $resolver->cascadeActualStartDates($project, $task);
        }

        $shouldRecalc = array_key_exists('progress_actual', $updateData)
            || array_key_exists('status', $updateData)
            || array_key_exists('start_date_actual', $updateData)
            || array_key_exists('end_date_actual', $updateData);

        if ($shouldRecalc) {
            (new ProjectProgressCalculator())->recalculateFromTask($task->refresh());
        }

        $fieldLabels = [
            'status' => 'Status',
            'weight' => 'Weight',
            'end_date_revise' => 'Revise end',
            'start_date_plan' => 'Plan start',
            'end_date_plan' => 'Plan end',
            'start_date_revise' => 'Revise start',
            'start_date_actual' => 'Actual start',
            'end_date_actual' => 'Actual end',
            'progress_actual' => 'Progress',
            'task_order' => 'Order',
        ];
        foreach ($updateData as $field => $newValue) {
            if (isset($fieldLabels[$field]) && $oldValues[$field] != $newValue) {
                $actionType = in_array($field, ['start_date_actual', 'end_date_actual', 'start_date_plan', 'end_date_plan', 'start_date_revise', 'end_date_revise']) ? 'bar_drag' : 'task_update';
                GanttChangeLogger::log(
                    $project,
                    auth()->user(),
                    $actionType,
                    $task,
                    null,
                    $fieldLabels[$field],
                    $oldValues[$field],
                    $newValue
                );
            }
        }

        return response()->json([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'weight' => $task->weight,
                'end_date_revise' => $task->end_date_revise?->format('M d'),
                'end_date_revise_raw' => $task->end_date_revise?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * List recent Gantt changes for a project (paginated, used by the Gantt UI)
     */
    public function ganttChanges(Request $request, Project $project)
    {
        $perPage = 5;
        $changes = GanttChangeLog::with(['user', 'task', 'phase'])
            ->where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $changes->map(function (GanttChangeLog $log) {
            $subject = $log->task?->task_name ?? ($log->phase?->phase_name ?? 'Project');
            return [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'Unknown',
                'action' => $log->action_type,
                'description' => $log->description ?? "{$subject}: {$log->field_name} changed",
                'created_at' => $log->created_at?->format('d M Y, H:i') ?? '-',
                'created_at_iso' => $log->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $changes->currentPage(),
                'last_page' => $changes->lastPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
                'next_page_url' => $changes->nextPageUrl(),
                'prev_page_url' => $changes->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Update a task dependency (set or remove predecessor) via drag-and-drop
     */
    public function updateDependency(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'predecessor_task_id' => 'nullable|integer|exists:project_tasks,id',
            'dependency_type' => 'required_with:predecessor_task_id|string|in:end_to_start,start_to_start',
        ]);

        $predecessorTaskId = $validated['predecessor_task_id'] ?? null;
        $dependencyType = $validated['dependency_type'] ?? 'end_to_start';

        if ($predecessorTaskId) {
            $resolver = new TaskDependencyResolver();
            $allTasks = $project->tasks()->get();

            try {
                $resolver->validatePredecessor($task, $predecessorTaskId, $allTasks);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $oldPredecessor = $task->predecessor_task_id;

        $task->update([
            'predecessor_task_id' => $predecessorTaskId,
            'dependency_type' => $predecessorTaskId ? $dependencyType : 'end_to_start',
        ]);

        if ($oldPredecessor != $predecessorTaskId) {
            GanttChangeLogger::log(
                $project,
                auth()->user(),
                'dependency_update',
                $task,
                null,
                'Dependency',
                $oldPredecessor ? ProjectTask::find($oldPredecessor)?->task_name : 'None',
                $predecessorTaskId ? ProjectTask::find($predecessorTaskId)?->task_name : 'None',
                "Dependency for '{$task->task_name}' changed"
            );
        }

        $resolver = new TaskDependencyResolver();
        $task->refresh();
        $resolver->cascadeActualStartDates($project, $task);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'predecessor_task_id' => $predecessorTaskId,
            'dependency_type' => $predecessorTaskId ? $dependencyType : 'end_to_start',
        ]);
    }

    /**
     * Delete a task
     */
    public function destroy(Request $request, Project $project, ProjectTask $task)
    {
        ProjectTask::where('predecessor_task_id', $task->id)->update(['predecessor_task_id' => null]);

        $phase = $task->phase;
        $taskName = $task->task_name;
        $task->delete();

        GanttChangeLogger::log(
            $project,
            auth()->user(),
            'task_delete',
            null,
            null,
            null,
            null,
            null,
            "Task '{$taskName}' deleted"
        );

        $calculator = new ProjectProgressCalculator();
        if ($phase) {
            $calculator->recalculatePhaseProgress($phase);
        }
        $calculator->recalculateProjectProgress($project);

        $query = ['tab' => $request->input('tab', 'tasks')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('admin.project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Task deleted successfully.');
    }

    /**
     * Reorder task_order values so the sequence stays consecutive with no duplicates
     * within the same project/phase when a task is created, moved, or updated.
     */
    private function reorderTasksForOrder(Project $project, ?int $oldPhaseId, ?int $oldOrder, ?int $newPhaseId, int $newOrder): int
    {
        $newOrder = max(1, $newOrder);

        // New task: shift existing tasks to make room
        if ($oldOrder === null) {
            $count = ProjectTask::where('project_id', $project->id)
                ->where('phase_id', $newPhaseId)
                ->count();
            if ($newOrder > $count + 1) {
                $newOrder = $count + 1;
            }
            ProjectTask::where('project_id', $project->id)
                ->where('phase_id', $newPhaseId)
                ->where('task_order', '>=', $newOrder)
                ->increment('task_order', 1);
            return $newOrder;
        }

        // Same phase: shift tasks between old and new order
        if ($oldPhaseId == $newPhaseId) {
            if ($oldOrder == $newOrder) {
                return $newOrder;
            }
            $count = ProjectTask::where('project_id', $project->id)
                ->where('phase_id', $newPhaseId)
                ->count();
            if ($newOrder > $count) {
                $newOrder = $count;
            }
            if ($newOrder > $oldOrder) {
                ProjectTask::where('project_id', $project->id)
                    ->where('phase_id', $newPhaseId)
                    ->where('task_order', '>', $oldOrder)
                    ->where('task_order', '<=', $newOrder)
                    ->decrement('task_order', 1);
            } else {
                ProjectTask::where('project_id', $project->id)
                    ->where('phase_id', $newPhaseId)
                    ->where('task_order', '>=', $newOrder)
                    ->where('task_order', '<', $oldOrder)
                    ->increment('task_order', 1);
            }
            return $newOrder;
        }

        // Phase changed: remove from old phase, then insert into new phase
        ProjectTask::where('project_id', $project->id)
            ->where('phase_id', $oldPhaseId)
            ->where('task_order', '>', $oldOrder)
            ->decrement('task_order', 1);

        $count = ProjectTask::where('project_id', $project->id)
            ->where('phase_id', $newPhaseId)
            ->count();
        if ($newOrder > $count + 1) {
            $newOrder = $count + 1;
        }
        ProjectTask::where('project_id', $project->id)
            ->where('phase_id', $newPhaseId)
            ->where('task_order', '>=', $newOrder)
            ->increment('task_order', 1);

        return $newOrder;
    }

    /**
     * Validate that the total weight of tasks within the same phase
     * does not exceed 100 after adding/updating this task.
     */
    private function validateTaskWeightSum(Project $project, ?int $phaseId, int $newWeight, ?int $excludeTaskId): void
    {
        // Only phases enforce a 100% weight budget; standalone tasks are unrestricted
        if (!$phaseId) {
            return;
        }

        $query = ProjectTask::where('project_id', $project->id)
            ->where('phase_id', $phaseId);

        if ($excludeTaskId) {
            $query->where('id', '!=', $excludeTaskId);
        }

        $currentSum = (int) $query->sum('weight');
        $total = $currentSum + $newWeight;

        if ($total > 100) {
            $phase = ProjectPhase::find($phaseId);
            $groupLabel = $phase ? $phase->phase_name : 'this phase';
            $message = sprintf(
                'Total weight for tasks in "%s" cannot exceed 100%%. Current total would be %d%%.',
                $groupLabel,
                $total
            );
            throw ValidationException::withMessages(['weight' => $message]);
        }
    }

    /**
     * Export Gantt chart as Excel (respects visibility toggles).
     */
    public function exportGanttExcel(Project $project, Request $request)
    {
        $project->load(['phases', 'phases.tasks' => function($q) {
            $q->with(['assignedTo'])->orderBy('task_order');
        }]);
        $project->load(['tasks' => function($q) {
            $q->with(['assignedTo'])->whereNull('phase_id')->orderBy('task_order');
        }]);

        $resolver = new TaskDependencyResolver();
        $effectiveDates = $resolver->resolve($project);

        $visible = [
            'plan' => $request->boolean('plan', true),
            'revise' => $request->boolean('revise', true),
            'actual' => $request->boolean('actual', true),
            'dependencies' => $request->boolean('dependencies', true),
        ];

        $zoom = $request->input('zoom', 'day');
        $exporter = new GanttExcelExport();
        $spreadsheet = $exporter->generate($project, $effectiveDates, $visible, $zoom);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = $project->project_name . ' - Gantt.xlsx';
        $filename = preg_replace('/[^\w\s\-\.]/', '', $filename);

        $tempFile = tempnam(sys_get_temp_dir(), 'gantt_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export Gantt chart as PDF (respects visibility toggles).
     */
    public function exportGanttPdf(Project $project, Request $request)
    {
        $project->load(['phases', 'phases.tasks' => function($q) {
            $q->with(['assignedTo'])->orderBy('task_order');
        }]);
        $project->load(['tasks' => function($q) {
            $q->with(['assignedTo'])->whereNull('phase_id')->orderBy('task_order');
        }]);

        $resolver = new TaskDependencyResolver();
        $effectiveDates = $resolver->resolve($project);

        $visible = [
            'plan' => $request->boolean('plan', true),
            'revise' => $request->boolean('revise', true),
            'actual' => $request->boolean('actual', true),
            'dependencies' => $request->boolean('dependencies', true),
        ];

        $phases = $project->phases;
        $standaloneTasks = $project->tasks;
        $zoom = $request->input('zoom', 'day');

        $logoPath = public_path('images/Logo TSSB.jpeg');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

        $pdf = Pdf::loadView('admin.project.pdf.gantt-export', compact(
            'project', 'phases', 'standaloneTasks', 'effectiveDates', 'visible', 'zoom', 'logoBase64'
        ))->setPaper('a4', 'landscape');

        $filename = $project->project_name . ' - Gantt.pdf';
        $filename = preg_replace('/[^\w\s\-\.]/', '', $filename);

        return $pdf->download($filename);
    }
}
