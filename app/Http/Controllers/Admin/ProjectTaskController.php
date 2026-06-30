<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectProgressLog;
use App\Models\ProjectTask;
use App\Services\ProjectProgressCalculator;
use App\Services\TaskDependencyResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectTaskController extends Controller
{
    /**
     * List tasks for a project
     */
    public function index(Project $project)
    {
        $tasks = $project->tasks()->with(['phase', 'assignedTo', 'comments.user', 'attachments.user'])->withCount('comments')->withCount('attachments')->orderBy('task_order')->get();
        $phases = $project->phases()->orderBy('phase_order')->get();

        $resolver = new TaskDependencyResolver();
        $effectiveDates = $resolver->resolve($project);

        return view('admin.project.tasks.index', compact('project', 'tasks', 'phases', 'effectiveDates'));
    }

    /**
     * Show create task form
     */
    public function create(Project $project, Request $request)
    {
        $phases = $project->phases()->orderBy('phase_order')->get();
        $tasks = $project->tasks()->orderBy('task_order')->get();
        $users = \App\Models\User::all();
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
            'predecessor_task_id' => 'nullable|exists:project_tasks,id',
            'task_name' => 'required|string|max:255',
            'task_order' => 'required|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'progress_plan' => 'nullable|integer|min:0|max:100',
            'progress_actual' => 'nullable|integer|min:0|max:100',
            'progress_revise' => 'nullable|integer|min:0|max:100',
            'weight' => 'required|integer|min:0|max:100',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
            'status' => 'nullable|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'remarks' => 'nullable|string',
        ]);

        $validated['project_id'] = $project->id;
        $validated['progress_plan'] = $validated['progress_plan'] ?? 0;
        $validated['progress_actual'] = $validated['progress_actual'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;

        $this->validateTaskWeightSum($project, $validated['phase_id'] ?? null, $validated['weight'], null);

        // Validate no cycle if predecessor is set
        if (!empty($validated['predecessor_task_id'])) {
            $resolver = new TaskDependencyResolver();
            $task = new ProjectTask($validated);
            $resolver->validatePredecessor($task, $validated['predecessor_task_id'], $project->tasks);
        }

        $task = ProjectTask::create($validated);

        // If the new task has a predecessor, calculate its revised dates based on predecessor
        if (!empty($validated['predecessor_task_id'])) {
            $resolver = new TaskDependencyResolver();
            $resolver->recalculateTaskReviseDates($task);
        }

        (new ProjectProgressCalculator())->recalculateFromTask($task);

        return redirect()->route('admin.project.projects.tasks.index', $project)
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
        $users = \App\Models\User::all();
        return view('admin.project.tasks.edit', compact('project', 'task', 'phases', 'tasks', 'users'));
    }

    /**
     * Update task
     */
    public function update(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'phase_id' => 'nullable|exists:project_phases,id',
            'predecessor_task_id' => 'nullable|exists:project_tasks,id|not_in:' . $task->id,
            'task_name' => 'required|string|max:255',
            'task_order' => 'required|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'progress_plan' => 'nullable|integer|min:0|max:100',
            'progress_actual' => 'nullable|integer|min:0|max:100',
            'progress_revise' => 'nullable|integer|min:0|max:100',
            'weight' => 'required|integer|min:0|max:100',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
            'status' => 'nullable|string|in:not_started,in_progress,completed,on_hold,cancelled',
            'remarks' => 'nullable|string',
        ]);

        $validated['progress_plan'] = $validated['progress_plan'] ?? 0;
        $validated['progress_actual'] = $validated['progress_actual'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;

        $this->validateTaskWeightSum($project, $validated['phase_id'] ?? null, $validated['weight'], $task->id);

        // Validate no cycle if predecessor is set
        if (!empty($validated['predecessor_task_id'])) {
            $resolver = new TaskDependencyResolver();
            $tempTask = clone $task;
            $tempTask->predecessor_task_id = $validated['predecessor_task_id'];
            $resolver->validatePredecessor($tempTask, $validated['predecessor_task_id'], $project->tasks);
        }

        $oldPredecessorId = $task->predecessor_task_id;
        $oldActualEnd = $task->end_date_actual ? $task->end_date_actual->format('Y-m-d') : null;
        $oldReviseEnd = $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : null;
        $oldPhaseId = $task->phase_id;

        $task->update($validated);

        $resolver = new TaskDependencyResolver();

        // If predecessor changed or set, recalculate this task's own revised dates
        if (!empty($validated['predecessor_task_id']) || $oldPredecessorId != ($validated['predecessor_task_id'] ?? null)) {
            $task->refresh();
            $resolver->recalculateTaskReviseDates($task);
        }

        // If this task's end dates changed, cascade revised dates to successors
        $newActualEnd = $validated['end_date_actual'] ?? null;
        $newReviseEnd = $validated['end_date_revise'] ?? null;
        if ($oldActualEnd !== $newActualEnd || $oldReviseEnd !== $newReviseEnd) {
            $task->refresh();
            $resolver->cascadeReviseDates($project, $task);
        }

        // Recalculate progress for affected phase(s) and project
        $calculator = new ProjectProgressCalculator();
        if ($oldPhaseId && $oldPhaseId != ($validated['phase_id'] ?? null)) {
            $oldPhase = ProjectPhase::find($oldPhaseId);
            if ($oldPhase) {
                $calculator->recalculatePhaseProgress($oldPhase);
            }
        }
        $calculator->recalculateFromTask($task->refresh());

        return redirect()->route('admin.project.projects.tasks.index', $project)
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
        ];

        $task->update($updateData);

        // Recalculate progress when progress changes
        if ($oldValues['progress_actual'] !== $validated['progress_actual']) {
            (new ProjectProgressCalculator())->recalculateFromTask($task->refresh());
        }

        // If actual or revise end dates changed, cascade revised dates to successors
        $newActualEnd = $validated['end_date_actual'] ?? null;
        $newReviseEnd = $validated['end_date_revise'] ?? null;
        if ($oldValues['end_date_actual'] !== $newActualEnd || $oldValues['end_date_revise'] !== $newReviseEnd) {
            $task->refresh();
            $resolver = new TaskDependencyResolver();
            $resolver->cascadeReviseDates($project, $task);
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
            }
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    /**
     * Delete a task
     */
    public function destroy(Project $project, ProjectTask $task)
    {
        ProjectTask::where('predecessor_task_id', $task->id)->update(['predecessor_task_id' => null]);

        $phase = $task->phase;
        $task->delete();

        $calculator = new ProjectProgressCalculator();
        if ($phase) {
            $calculator->recalculatePhaseProgress($phase);
        }
        $calculator->recalculateProjectProgress($project);

        return redirect()->route('admin.project.projects.show', $project)
            ->with('success', 'Task deleted successfully.');
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
}
