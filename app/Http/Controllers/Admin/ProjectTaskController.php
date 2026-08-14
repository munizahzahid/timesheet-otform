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
        return redirect()->to(route('project.projects.show', $project) . '?tab=tasks');
    }

    /**
     * Get tasks for Kanban refresh (JSON response)
     */
    public function kanbanTasks(Request $request, Project $project)
    {
        $resolver = new TaskDependencyResolver();
        $effectiveDates = $resolver->resolve($project);

        $tasks = $project->tasks()
            ->with(['phase', 'assignedTo', 'predecessorTask', 'comments', 'attachments'])
            ->orderBy('task_order')
            ->get();

        $kanbanTasks = $tasks->map(function ($task) use ($project, $effectiveDates) {
            $effective = $effectiveDates[$task->id] ?? [];
            return [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'status' => $task->status ?? 'not_started',
                'remarks' => $task->remarks,
                'progress_actual' => $task->progress_actual,
                'weight' => $task->weight,
                'end_date_revise' => $task->end_date_revise?->format('Y-m-d'),
                'end_date_revise_formatted' => $task->end_date_revise?->format('M d'),
                'end_date_plan_formatted' => $task->end_date_plan?->format('M d'),
                'phase_id' => $task->phase_id,
                'phase_name' => $task->phase?->phase_name,
                'assigned_to_name' => $task->assignedTo?->name,
                'assigned_to_url' => $task->assignedTo ? route('project.assigned-tasks', $task->assignedTo) : null,
                'comments_count' => $task->comments_count,
                'attachments_count' => $task->attachments_count,
                'plan_delay_days' => $effective['plan_delay_days'] ?? 0,
                'comments' => $task->comments->map(function ($comment) use ($project, $task) {
                    return [
                        'id' => $comment->id,
                        'user_id' => $comment->user_id,
                        'user_name' => $comment->user?->name,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at->diffForHumans(),
                        'is_owner' => $comment->user_id === auth()->id(),
                        'delete_url' => route('project.projects.tasks.comments.destroy', [$project, $task, $comment]),
                    ];
                })->values()->toArray(),
                'attachments' => $task->attachments->map(function ($attachment) use ($project, $task) {
                    return [
                        'id' => $attachment->id,
                        'user_id' => $attachment->user_id,
                        'file_name' => $attachment->file_name,
                        'show_url' => route('project.projects.tasks.attachments.show', [$project, $task, $attachment]),
                        'is_owner' => $attachment->user_id === auth()->id(),
                        'delete_url' => route('project.projects.tasks.attachments.destroy', [$project, $task, $attachment]),
                    ];
                })->values()->toArray(),
                'update_url' => route('project.projects.tasks.inline-update', [$project, $task]),
                'show_url' => route('project.projects.tasks.show', [$project, $task]),
                'edit_url' => route('project.projects.tasks.edit', [$project, $task]),
                'delete_url' => route('project.projects.tasks.destroy', [$project, $task]),
                'task_order' => $task->task_order,
                'assigned_to' => $task->assigned_to,
                'predecessor_task_id' => $task->predecessor_task_id,
                'dependency_type' => $task->dependency_type ?? 'end_to_start',
                'start_date_plan' => $task->start_date_plan?->format('Y-m-d'),
                'end_date_plan' => $task->end_date_plan?->format('Y-m-d'),
                'start_date_actual' => $task->start_date_actual?->format('Y-m-d'),
                'end_date_actual' => $task->end_date_actual?->format('Y-m-d'),
                'start_date_revise' => $task->start_date_revise?->format('Y-m-d'),
                'end_date_revise' => $task->end_date_revise?->format('Y-m-d'),
                'comment_store_url' => route('project.projects.tasks.comments.store', [$project, $task]) . '?' . (request()->getQueryString() ?: 'tab=tasks'),
                'attachment_store_url' => route('project.projects.tasks.attachments.store', [$project, $task]) . '?' . (request()->getQueryString() ?: 'tab=tasks'),
            ];
        })->values()->toArray();

        return response()->json([
            'success' => true,
            'tasks' => $kanbanTasks,
        ]);
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

        // Determine min/max plan dates for validation (use project dates as default)
        $minPlanStart = $project->start_date_plan ? $project->start_date_plan->format('Y-m-d') : null;
        $maxPlanEnd = $project->end_date_plan ? $project->end_date_plan->format('Y-m-d') : null;

        return view('admin.project.tasks.create', compact('project', 'phases', 'tasks', 'users', 'defaultPhaseId', 'minPlanStart', 'maxPlanEnd'));
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
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        // Centralized dependency date validation
        $tempTask = new ProjectTask($validated);
        $predecessor = !empty($validated['predecessor_task_id']) ? $project->tasks->firstWhere('id', $validated['predecessor_task_id']) : null;
        $dateFields = ['start_date_plan', 'end_date_plan', 'start_date_revise', 'end_date_revise', 'start_date_actual', 'end_date_actual'];
        $proposedDates = array_intersect_key($validated, array_flip($dateFields));
        $dateErrors = $resolver->validateTaskDates($tempTask, $proposedDates, $predecessor);

        if (!empty($dateErrors)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $dateErrors, 'message' => 'The proposed dates conflict with dependency rules.'], 422);
            }
            return redirect()->back()->withInput()->with('error', 'The proposed dates conflict with dependency rules.');
        }

        // Plan date constraint validation (parent phase or project)
        $tempTaskForPlan = new ProjectTask($validated);
        $planDateErrors = $resolver->validatePlanDateConstraints($tempTaskForPlan, $proposedDates, $project);
        if (!empty($planDateErrors)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $planDateErrors, 'message' => 'The proposed dates conflict with parent/project plan dates.'], 422);
            }
            return redirect()->back()->withInput()->with('error', 'The proposed dates conflict with parent/project plan dates.');
        }

        $task = ProjectTask::create($validated);

        // Hybrid sync logic for store: if status is "completed", set progress to 100% and actual dates
        if ($task->status === 'completed') {
            $task->update(['progress_actual' => 100]);
            if (!$task->end_date_actual) {
                $task->update(['end_date_actual' => now()->format('Y-m-d')]);
            }
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $task->update(['start_date_actual' => now()->format('Y-m-d')]);
            }
        }

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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task]);
        }

        $query = ['tab' => $request->input('tab', 'tasks')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Subtask created successfully.');
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

        // Determine min/max plan dates for validation
        $parentPhase = $task->phase_id ? $phases->firstWhere('id', $task->phase_id) : null;
        if ($parentPhase) {
            $minPlanStart = $parentPhase->start_date_plan ? $parentPhase->start_date_plan->format('Y-m-d') : null;
            $maxPlanEnd = $parentPhase->end_date_plan ? $parentPhase->end_date_plan->format('Y-m-d') : null;
        } else {
            $minPlanStart = $project->start_date_plan ? $project->start_date_plan->format('Y-m-d') : null;
            $maxPlanEnd = $project->end_date_plan ? $project->end_date_plan->format('Y-m-d') : null;
        }

        return view('admin.project.tasks.edit', compact('project', 'task', 'phases', 'tasks', 'users', 'minPlanStart', 'maxPlanEnd'));
    }

    /**
     * Update task
     */
    public function update(Request $request, Project $project, ProjectTask $task)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()->toArray()], 422);
            }
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $validated = $validator->validated();

        $validated['progress_actual'] = $validated['progress_actual'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['is_actual_start_manual'] = !empty($validated['start_date_actual']);

        try {
            $this->validateTaskWeightSum($project, $validated['phase_id'] ?? null, $validated['weight'], $task->id);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exceed weight percentage. ' . collect($e->errors())->flatten()->first(),
                ], 422);
            }
            throw $e;
        }

        $resolver = new TaskDependencyResolver();
        if (!empty($validated['predecessor_task_id'])) {
            $tempTask = clone $task;
            $tempTask->predecessor_task_id = $validated['predecessor_task_id'];
            $tempTask->dependency_type = $validated['dependency_type'] ?? 'end_to_start';
            try {
                $resolver->validatePredecessor($tempTask, $validated['predecessor_task_id'], $project->tasks);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        // Centralized dependency date validation
        $tempTask = clone $task;
        $tempTask->predecessor_task_id = $validated['predecessor_task_id'] ?? $task->predecessor_task_id;
        $tempTask->dependency_type = $validated['dependency_type'] ?? $task->dependency_type;
        $predecessor = $tempTask->predecessor_task_id ? $project->tasks->firstWhere('id', $tempTask->predecessor_task_id) : null;
        $dateFields = ['start_date_plan', 'end_date_plan', 'start_date_revise', 'end_date_revise', 'start_date_actual', 'end_date_actual'];
        $proposedDates = array_intersect_key($validated, array_flip($dateFields));
        $dateErrors = $resolver->validateTaskDates($tempTask, $proposedDates, $predecessor);

        if (!empty($dateErrors)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $dateErrors, 'message' => 'The proposed dates conflict with dependency rules.'], 422);
            }
            return redirect()->back()->withInput()->with('error', 'The proposed dates conflict with dependency rules.');
        }

        // Plan date constraint validation (parent phase or project)
        $planDateErrors = $resolver->validatePlanDateConstraints($task, $proposedDates, $project);
        if (!empty($planDateErrors)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $planDateErrors, 'message' => 'The proposed dates conflict with parent/project plan dates.'], 422);
            }
            return redirect()->back()->withInput()->with('error', 'The proposed dates conflict with parent/project plan dates.');
        }

        // Dependency validation for status changes (full update method)
        if (array_key_exists('status', $validated) && $task->predecessor_task_id) {
            $predecessor = $project->tasks->firstWhere('id', $task->predecessor_task_id);
            $dependencyType = $task->dependency_type ?? 'end_to_start';
            $newStatus = $validated['status'];
            $oldStatus = $task->status ?? 'not_started';

            // For ES (End-to-Start): successor cannot start until predecessor is completed
            if ($dependencyType === 'end_to_start') {
                // Check if trying to change from not_started to in_progress/completed/on_hold/cancelled
                if ($oldStatus === 'not_started' && $newStatus !== 'not_started') {
                    // Predecessor must be completed
                    if (!$predecessor || $predecessor->status !== 'completed') {
                        if ($request->wantsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'This task cannot start because its predecessor has not been completed yet.',
                            ], 422);
                        }
                        return redirect()->back()->withInput()->with('error', 'This task cannot start because its predecessor has not been completed yet.');
                    }
                }
                // Check if trying to change to completed when predecessor is not completed
                if ($newStatus === 'completed') {
                    if (!$predecessor || $predecessor->status !== 'completed') {
                        if ($request->wantsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'This task cannot be completed because its predecessor has not been completed yet.',
                            ], 422);
                        }
                        return redirect()->back()->withInput()->with('error', 'This task cannot be completed because its predecessor has not been completed yet.');
                    }
                }
            }
        }

        $oldPlanStart = $task->start_date_plan ? $task->start_date_plan->copy() : null;
        $oldPlanEnd = $task->end_date_plan ? $task->end_date_plan->copy() : null;
        $oldActualEnd = $task->end_date_actual ? $task->end_date_actual->format('Y-m-d') : null;
        $oldReviseEnd = $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : null;
        $oldPhaseId = $task->phase_id;
        $oldOrder = $task->task_order;

        // Capture old status and progress BEFORE updating the task
        $oldStatus = $task->status ?? 'not_started';
        $oldProgress = $task->progress_actual ?? 0;

        $validated['task_order'] = $this->reorderTasksForOrder(
            $project,
            $oldPhaseId,
            $oldOrder,
            $validated['phase_id'] ?? null,
            (int) $validated['task_order']
        );

        $task->update($validated);

        // Hybrid sync logic for the full update method
        $newStatus = $validated['status'] ?? $oldStatus;
        $newProgress = $validated['progress_actual'] ?? $oldProgress;

        $syncUpdateData = [];

        // When status changes to "completed"
        if (array_key_exists('status', $validated) && $validated['status'] === 'completed' && $oldStatus !== 'completed') {
            // Set progress to 100% only if user didn't explicitly set a new progress
            if (!array_key_exists('progress_actual', $validated)) {
                $syncUpdateData['progress_actual'] = 100;
            }
            // Set end_date_actual to today if not already set
            if (!$task->end_date_actual) {
                $syncUpdateData['end_date_actual'] = now()->format('Y-m-d');
            }
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $syncUpdateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // Determine if user explicitly changed the progress (not just inherited the existing value)
        $userChangedProgress = array_key_exists('progress_actual', $validated) && (int) $validated['progress_actual'] !== $oldProgress;

        // When status changes from "completed" to "in_progress"
        if (array_key_exists('status', $validated) && $oldStatus === 'completed' && $newStatus === 'in_progress') {
            // Clear end_date_actual to reopen the task
            $syncUpdateData['end_date_actual'] = null;
            // Always set progress to 99% when changing from completed to in_progress
            // unless user explicitly set a different value (not 0 or 100)
            if (!$userChangedProgress || $validated['progress_actual'] === 0 || $validated['progress_actual'] === 100) {
                $syncUpdateData['progress_actual'] = 99;
            }
            // Do NOT clear start_date_actual - keep the existing start date
        }

        // When status changes from "completed" to "not_started"
        if (array_key_exists('status', $validated) && $oldStatus === 'completed' && $newStatus === 'not_started') {
            // Clear end_date_actual to reopen the task
            $syncUpdateData['end_date_actual'] = null;
            // Clear start_date_actual when resetting to not_started
            $syncUpdateData['start_date_actual'] = null;
            // If progress was 100% and user didn't explicitly change progress, set to 0%
            if ($oldProgress === 100 && !$userChangedProgress) {
                $syncUpdateData['progress_actual'] = 0;
            }
        }

        // When status changes from "completed" to other statuses (on_hold, cancelled)
        if (array_key_exists('status', $validated) && $oldStatus === 'completed' && !in_array($newStatus, ['completed', 'in_progress', 'not_started'])) {
            // Clear end_date_actual to reopen the task
            $syncUpdateData['end_date_actual'] = null;
            // If progress was 100% and user didn't explicitly change progress, set to 99%
            if ($oldProgress === 100 && !$userChangedProgress) {
                $syncUpdateData['progress_actual'] = 99;
            }
            // Do NOT clear start_date_actual - keep the existing start date
        }

        // When status changes from "not_started" to any other status
        if (array_key_exists('status', $validated) && $oldStatus === 'not_started' && $newStatus !== 'not_started') {
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $syncUpdateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes to 100%
        if (array_key_exists('progress_actual', $validated) && $validated['progress_actual'] === 100 && $oldProgress < 100) {
            // Set status to "completed" if not already
            if ($oldStatus !== 'completed') {
                $syncUpdateData['status'] = 'completed';
            }
            // Set end_date_actual to today if not already set
            if (!$task->end_date_actual) {
                $syncUpdateData['end_date_actual'] = now()->format('Y-m-d');
            }
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $syncUpdateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes from 0 to > 0 (task is starting)
        if (array_key_exists('progress_actual', $validated) && $validated['progress_actual'] > 0 && $oldProgress === 0) {
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $syncUpdateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes from 100% to < 100%
        if (array_key_exists('progress_actual', $validated) && $validated['progress_actual'] < 100 && $oldProgress === 100) {
            // Set status to "in_progress" if it was "completed"
            if ($oldStatus === 'completed') {
                $syncUpdateData['status'] = 'in_progress';
            }
            // Clear end_date_actual
            $syncUpdateData['end_date_actual'] = null;
        }

        // When progress changes to 0% (task is being reset to not_started)
        // Only clear actual dates if the user explicitly set progress to 0 AND status is not being changed from completed to in_progress
        $isStatusChangeFromCompletedToInProgress = array_key_exists('status', $validated) && $oldStatus === 'completed' && $newStatus === 'in_progress';
        if (array_key_exists('progress_actual', $validated) && $validated['progress_actual'] === 0 && $oldProgress > 0 && !$isStatusChangeFromCompletedToInProgress) {
            // Clear all actual dates
            $syncUpdateData['start_date_actual'] = null;
            $syncUpdateData['end_date_actual'] = null;
        }

        // Apply sync updates if any
        if (!empty($syncUpdateData)) {
            $task->update($syncUpdateData);
        }

        // If progress drops below 100%, clear the actual end date so successors are treated as incomplete
        if ($validated['progress_actual'] < 100 && $oldActualEnd) {
            $task->update(['end_date_actual' => null]);
        }

        // ES (End-to-Start) dependency cascading for status changes (full update method)
        // When predecessor changes status, cascade to successors based on ES rules
        // Call this BEFORE cascadeActualStartDates to ensure successors are reset properly
        // Remove dependency_type check - this task is the predecessor, so cascade to all its successors
        if (array_key_exists('status', $validated) && $oldStatus !== $newStatus) {
            // Refresh the task to get the latest data after update
            $task->refresh();
            $this->cascadeESStatusChange($project, $task, $oldStatus, $newStatus);
        }

        // SS (Start-to-Start) dependency cascading for status changes (full update method)
        // When predecessor changes status (any status change), cascade to successors
        // Remove dependency_type check - this task is the predecessor, so cascade to all its successors
        if (array_key_exists('status', $validated) && $oldStatus !== $newStatus) {
            // Refresh the task to get the latest data after update
            $task->refresh();
            $this->cascadeSSStatusChange($project, $task, $newStatus);
        }

        // Propagate plan date changes to successor tasks, then recalculate actual starts
        $resolver->cascadePlanDates($project, $task, $oldPlanStart, $oldPlanEnd);

        $task->refresh();

        // Cascade actual dates if status changed to/from completed (which affects end_date_actual)
        $shouldCascadeActual = false;
        if (array_key_exists('status', $validated)) {
            if (($validated['status'] === 'completed' && $oldStatus !== 'completed') ||
                ($validated['status'] !== 'completed' && $oldStatus === 'completed')) {
                $shouldCascadeActual = true;
            }
        }
        if (array_key_exists('end_date_actual', $syncUpdateData) || $shouldCascadeActual) {
            $resolver->cascadeActualStartDates($project, $task);
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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task' => $task]);
        }

        $query = ['tab' => $request->input('tab', 'tasks')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Subtask updated successfully.');
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

        // If progress drops below 100%, clear the actual end date so successors are treated as incomplete
        if ($validated['progress_actual'] < 100 && $oldValues['end_date_actual']) {
            $updateData['end_date_actual'] = null;
        }

        $task->update($updateData);

        $resolver = new TaskDependencyResolver();
        $task->refresh();
        $resolver->cascadeActualStartDates($project, $task);

        // Recalculate progress when progress or plan dates change
        if ($oldValues['progress_actual'] !== $validated['progress_actual']
            || $oldValues['start_date_plan'] !== $validated['start_date_plan']
            || $oldValues['end_date_plan'] !== $validated['end_date_plan']) {
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

        return redirect()->back()->with('success', 'Subtask updated successfully.');
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

        $resolver = new TaskDependencyResolver();

        // Centralized dependency date validation for inline date changes
        $proposedDateFields = array_intersect_key($updateData, array_flip(['start_date_plan', 'end_date_plan', 'start_date_revise', 'end_date_revise', 'start_date_actual', 'end_date_actual']));
        if (!empty($proposedDateFields)) {
            $predecessor = $task->predecessor_task_id ? $project->tasks->firstWhere('id', $task->predecessor_task_id) : null;
            $dateErrors = $resolver->validateTaskDates($task, $proposedDateFields, $predecessor);

            if (!empty($dateErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The proposed dates conflict with dependency rules.',
                    'errors' => $dateErrors,
                ], 422);
            }

            // Plan date constraint validation (parent phase or project)
            $planDateErrors = $resolver->validatePlanDateConstraints($task, $proposedDateFields, $project);
            if (!empty($planDateErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The proposed dates conflict with parent/project plan dates.',
                    'errors' => $planDateErrors,
                ], 422);
            }
        }

        // Dependency validation for status changes
        if (array_key_exists('status', $updateData) && $task->predecessor_task_id) {
            $predecessor = $project->tasks->firstWhere('id', $task->predecessor_task_id);
            $dependencyType = $task->dependency_type ?? 'end_to_start';
            $newStatus = $updateData['status'];
            $oldStatus = $task->status ?? 'not_started';

            // For ES (End-to-Start): successor cannot start until predecessor is completed
            if ($dependencyType === 'end_to_start') {
                // Check if trying to change from not_started to in_progress/completed/on_hold/cancelled
                if ($oldStatus === 'not_started' && $newStatus !== 'not_started') {
                    // Predecessor must be completed
                    if (!$predecessor || $predecessor->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'This task cannot start because its predecessor has not been completed yet.',
                        ], 422);
                    }
                }
                // Check if trying to change to completed when predecessor is not completed
                if ($newStatus === 'completed') {
                    if (!$predecessor || $predecessor->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'This task cannot be completed because its predecessor has not been completed yet.',
                        ], 422);
                    }
                }
            }
        }

        // Hybrid sync logic: Status and progress affect each other
        $oldStatus = $task->status ?? 'not_started';
        $oldProgress = $task->progress_actual ?? 0;
        $newStatus = $updateData['status'] ?? $oldStatus;
        $newProgress = $updateData['progress_actual'] ?? $oldProgress;

        // Determine if user explicitly changed the progress (not just inherited the existing value)
        $userChangedProgress = array_key_exists('progress_actual', $updateData) && (int) $updateData['progress_actual'] !== $oldProgress;

        // When status changes to "completed"
        if (array_key_exists('status', $updateData) && $updateData['status'] === 'completed' && $oldStatus !== 'completed') {
            // Set progress to 100% only if user didn't explicitly change progress
            if (!$userChangedProgress) {
                $updateData['progress_actual'] = 100;
                $newProgress = 100;
            }
            // Set end_date_actual to today if not already set
            if (!$task->end_date_actual) {
                $updateData['end_date_actual'] = now()->format('Y-m-d');
            }
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $updateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When status changes from "completed" to "in_progress"
        if (array_key_exists('status', $updateData) && $oldStatus === 'completed' && $newStatus === 'in_progress') {
            // Clear end_date_actual to reopen the task
            $updateData['end_date_actual'] = null;
            // If progress was 100% and user didn't explicitly change progress, set to 99%
            if ($oldProgress === 100 && !$userChangedProgress) {
                $updateData['progress_actual'] = 99;
                $newProgress = 99;
            }
            // Do NOT clear start_date_actual - keep the existing start date
        }

        // When status changes from "completed" to "not_started"
        if (array_key_exists('status', $updateData) && $oldStatus === 'completed' && $newStatus === 'not_started') {
            // Clear end_date_actual to reopen the task
            $updateData['end_date_actual'] = null;
            // Clear start_date_actual when resetting to not_started
            $updateData['start_date_actual'] = null;
            // If progress was 100% and user didn't explicitly change progress, set to 0%
            if ($oldProgress === 100 && !$userChangedProgress) {
                $updateData['progress_actual'] = 0;
                $newProgress = 0;
            }
        }

        // When status changes from "completed" to other statuses (on_hold, cancelled)
        if (array_key_exists('status', $updateData) && $oldStatus === 'completed' && !in_array($newStatus, ['completed', 'in_progress', 'not_started'])) {
            // Clear end_date_actual to reopen the task
            $updateData['end_date_actual'] = null;
            // If progress was 100% and user didn't explicitly change progress, set to 99%
            if ($oldProgress === 100 && !$userChangedProgress) {
                $updateData['progress_actual'] = 99;
                $newProgress = 99;
            }
            // Do NOT clear start_date_actual - keep the existing start date
        }

        // When status changes to "not_started" (from non-completed status)
        if (array_key_exists('status', $updateData) && $updateData['status'] === 'not_started' && $oldStatus !== 'completed') {
            // Clear all actual dates
            $updateData['start_date_actual'] = null;
            $updateData['end_date_actual'] = null;
            // Set progress to 0% only if user didn't explicitly change progress
            if (!$userChangedProgress) {
                $updateData['progress_actual'] = 0;
                $newProgress = 0;
            }
        }

        // When status changes from "not_started" to any other status
        if (array_key_exists('status', $updateData) && $oldStatus === 'not_started' && $newStatus !== 'not_started') {
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $updateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes to 100%
        if (array_key_exists('progress_actual', $updateData) && $updateData['progress_actual'] === 100 && $oldProgress < 100) {
            // Set status to "completed" if not already
            if ($oldStatus !== 'completed') {
                $updateData['status'] = 'completed';
                $newStatus = 'completed';
            }
            // Set end_date_actual to today if not already set
            if (!$task->end_date_actual) {
                $updateData['end_date_actual'] = now()->format('Y-m-d');
            }
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $updateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes from 0 to > 0 (task is starting)
        if (array_key_exists('progress_actual', $updateData) && $updateData['progress_actual'] > 0 && $oldProgress === 0) {
            // Set start_date_actual to today only if not already set (preserve manual start dates)
            if (!$task->start_date_actual) {
                $updateData['start_date_actual'] = now()->format('Y-m-d');
            }
        }

        // When progress changes from 100% to < 100%
        if (array_key_exists('progress_actual', $updateData) && $updateData['progress_actual'] < 100 && $oldProgress === 100) {
            // Set status to "in_progress" if it was "completed"
            if ($oldStatus === 'completed') {
                $updateData['status'] = 'in_progress';
                $newStatus = 'in_progress';
            }
            // Clear end_date_actual
            $updateData['end_date_actual'] = null;
        }

        // When progress changes to 0% (task is being reset to not_started)
        if (array_key_exists('progress_actual', $updateData) && $updateData['progress_actual'] === 0 && $oldProgress > 0) {
            // Clear all actual dates
            $updateData['start_date_actual'] = null;
            $updateData['end_date_actual'] = null;
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

        // If progress drops below 100% and the task has an actual end date, clear it so successors are treated as incomplete
        $shouldCascadeActual = false;
        if (array_key_exists('progress_actual', $updateData) && $updateData['progress_actual'] < 100 && $task->end_date_actual) {
            $updateData['end_date_actual'] = null;
            $shouldCascadeActual = true;
        }

        // If end_date_actual is set (task is now complete), cascade to successors
        if (array_key_exists('end_date_actual', $updateData) && !empty($updateData['end_date_actual'])) {
            $shouldCascadeActual = true;
        }

        // If end_date_actual is cleared (task is no longer complete), cascade to successors
        if (array_key_exists('end_date_actual', $updateData) && $updateData['end_date_actual'] === null && $task->end_date_actual) {
            $shouldCascadeActual = true;
        }

        // If status changes to/from "completed", cascade to successors (because we set/clear end_date_actual)
        if (array_key_exists('status', $updateData)) {
            if (($updateData['status'] === 'completed' && $oldStatus !== 'completed') ||
                ($updateData['status'] !== 'completed' && $oldStatus === 'completed')) {
                $shouldCascadeActual = true;
            }
        }

        $oldValues = $task->getOriginal();

        if (!empty($updateData)) {
            $task->update($updateData);
        }

        $task->refresh();

        // ES (End-to-Start) dependency cascading for status changes
        // When predecessor changes status, cascade to successors based on ES rules
        // Call this BEFORE cascadeActualStartDates to ensure successors are reset properly
        // Remove dependency_type check - this task is the predecessor, so cascade to all its successors
        if (array_key_exists('status', $updateData) && $oldStatus !== $newStatus) {
            // Refresh the task to get the latest data after update
            $task->refresh();
            $this->cascadeESStatusChange($project, $task, $oldStatus, $newStatus);
        }

        // SS (Start-to-Start) dependency cascading for status changes
        // When predecessor changes status (any status change except to/from not_started), cascade to successors
        // Remove dependency_type check - this task is the predecessor, so cascade to all its successors
        if (array_key_exists('status', $updateData) && $oldStatus !== $newStatus) {
            // Refresh the task to get the latest data after update
            $task->refresh();
            $this->cascadeSSStatusChange($project, $task, $newStatus);
        }

        if (array_key_exists('start_date_plan', $updateData) || array_key_exists('end_date_plan', $updateData)) {
            $resolver->cascadePlanDates($project, $task, $oldPlanStart, $oldPlanEnd);
        }

        if (array_key_exists('start_date_actual', $updateData) || $shouldCascadeActual) {
            $resolver->cascadeActualStartDates($project, $task);
        }

        $shouldRecalc = array_key_exists('progress_actual', $updateData)
            || array_key_exists('status', $updateData)
            || array_key_exists('start_date_actual', $updateData)
            || array_key_exists('end_date_actual', $updateData)
            || array_key_exists('start_date_plan', $updateData)
            || array_key_exists('end_date_plan', $updateData);

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
     * Cascade SS (Start-to-Start) status change to successors
     * When predecessor changes status, successors follow the same status (except completed)
     * Exception: predecessor changing to "completed" does NOT cascade status to successor
     * If predecessor changes back to "not_started", successors also reset to "not_started"
     * When predecessor changes from "not_started" to any status, successor follows predecessor's actual start
     */
    private function cascadeSSStatusChange(Project $project, ProjectTask $predecessor, string $newStatus): void
    {
        // Don't cascade if predecessor is changing to "completed"
        if ($newStatus === 'completed') {
            return;
        }

        // Ensure predecessor has the latest data
        $predecessor->refresh();

        $tasks = $project->tasks()->with('predecessorTask')->get();
        $successors = $tasks->where('predecessor_task_id', $predecessor->id)
            ->where('dependency_type', 'start_to_start');

        foreach ($successors as $successor) {
            if ($newStatus === 'not_started') {
                // Predecessor is resetting to not_started, so successor should also reset
                $successor->update([
                    'start_date_actual' => null,
                    'end_date_actual' => null,
                    'status' => 'not_started',
                    'progress_actual' => 0,
                    'is_actual_start_manual' => false,
                ]);
            } else {
                // Predecessor is changing to a non-completed status
                // Only cascade if successor is still not_started
                if ($successor->status === 'not_started') {
                    // Successor follows predecessor's actual start (SS dependency)
                    $startDate = $predecessor->start_date_actual ?: now()->format('Y-m-d');
                    $successor->update([
                        'start_date_actual' => $startDate,
                        'status' => $newStatus, // Follow the predecessor's status
                        'is_actual_start_manual' => false,
                    ]);
                }
            }
        }
    }

    /**
     * Cascade ES (End-to-Start) status change to successors
     * When predecessor changes status, cascade to successors based on ES rules
     */
    private function cascadeESStatusChange(Project $project, ProjectTask $predecessor, string $oldStatus, string $newStatus): void
    {
        // Ensure predecessor has the latest data
        $predecessor->refresh();

        $logPath = storage_path('logs/cascade_es_debug.log');
        $log = function ($line) use ($logPath) {
            file_put_contents($logPath, '[' . now()->format('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        };
        $log('=== cascadeESStatusChange ===');
        $log('Predecessor id=' . $predecessor->id . ' name=' . $predecessor->task_name . ' oldStatus=' . $oldStatus . ' newStatus=' . $newStatus);
        $log('Predecessor actual_start=' . ($predecessor->start_date_actual?->format('Y-m-d') ?? 'null') . ' actual_end=' . ($predecessor->end_date_actual?->format('Y-m-d') ?? 'null') . ' plan_start=' . ($predecessor->start_date_plan?->format('Y-m-d') ?? 'null') . ' plan_end=' . ($predecessor->end_date_plan?->format('Y-m-d') ?? 'null'));

        $tasks = $project->tasks()->with('predecessorTask')->get();
        $successors = $tasks->where('predecessor_task_id', $predecessor->id)
            ->where('dependency_type', 'end_to_start');

        foreach ($successors as $successor) {
            // Predecessor: completed → in_progress/on_hold/cancelled/not_started
            // Successor should reset to not_started with cleared dates and progress
            // Always reset regardless of is_actual_start_manual flag
            if ($oldStatus === 'completed' && $newStatus !== 'completed') {
                $successor->updateQuietly([
                    'start_date_actual' => null,
                    'end_date_actual' => null,
                    'status' => 'not_started',
                    'progress_actual' => 0,
                    'is_actual_start_manual' => false,
                ]);
            }

            // Predecessor: in_progress → completed
            // Successor should set actual start to predecessor's actual end + gap
            // This always runs when predecessor completes, regardless of successor's current status
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $log('--- successor id=' . $successor->id . ' name=' . $successor->task_name . ' status=' . $successor->status . ' current_start=' . ($successor->start_date_actual?->format('Y-m-d') ?? 'null') . ' current_end=' . ($successor->end_date_actual?->format('Y-m-d') ?? 'null') . ' plan_start=' . ($successor->start_date_plan?->format('Y-m-d') ?? 'null'));
                $predecessorEndDate = $predecessor->end_date_actual ?: now()->format('Y-m-d');
                // Preserve the original gap: successor.actual_start = predecessor.actual_end + gap
                // gap = successor.plan_start - predecessor.plan_end (always non-negative for ES)
                if ($predecessor->end_date_plan && $successor->start_date_plan) {
                    $gap = (int) $predecessor->end_date_plan->diffInDays($successor->start_date_plan, false);
                    $gap = max(0, $gap);
                    $successorStartDate = \Carbon\Carbon::parse($predecessorEndDate)->addDays($gap)->format('Y-m-d');
                    $log('Using plan gap: gap=' . $gap . ' predecessorEnd=' . $predecessorEndDate . ' => newStart=' . $successorStartDate);
                } else {
                    $successorStartDate = date('Y-m-d', strtotime($predecessorEndDate . ' +1 day'));
                    $log('Falling back to +1 day: predecessorEnd=' . $predecessorEndDate . ' => newStart=' . $successorStartDate);
                }

                // Only update the actual start if it changed
                if ($successor->start_date_actual?->format('Y-m-d') !== $successorStartDate) {
                    $updateData = ['start_date_actual' => $successorStartDate];
                    if ($successor->start_date_actual && $successor->end_date_actual) {
                        // Keep the same duration by shifting the end date too
                        $duration = $successor->start_date_actual->diffInDays($successor->end_date_actual);
                        $updateData['end_date_actual'] = \Carbon\Carbon::parse($successorStartDate)->addDays($duration)->format('Y-m-d');
                    }
                    // Only set status to in_progress if successor is not yet started
                    if ($successor->status === 'not_started') {
                        $updateData['status'] = 'in_progress';
                    }
                    $updateData['is_actual_start_manual'] = false;
                    $successor->update($updateData);
                    $log('Updated successor with: ' . json_encode($updateData));
                } else {
                    $log('No update needed, current start already ' . $successorStartDate);
                }
            }
        }
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
        return redirect()->to(route('project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Subtask deleted successfully.');
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
