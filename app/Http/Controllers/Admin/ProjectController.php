<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProgressLog;
use App\Services\TaskDependencyResolver;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Project Executive Dashboard
     */
    public function dashboard()
    {
        $totalProjects = Project::count();
        $activeProjects = Project::where('status', 'active')->count();
        $completedProjects = Project::where('status', 'completed')->count();
        $delayedProjects = Project::where('status', 'delayed')->count();

        $projects = Project::latest()->get();
        $recentLogs = ProjectProgressLog::with(['project', 'changedBy'])
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('admin.project.dashboard', compact(
            'totalProjects',
            'activeProjects',
            'completedProjects',
            'delayedProjects',
            'projects',
            'recentLogs'
        ));
    }

    /**
     * Project List
     */
    public function index()
    {
        $projects = Project::latest()->get();
        return view('admin.project.index', compact('projects'));
    }

    /**
     * Show create project form
     */
    public function create()
    {
        return view('admin.project.create');
    }

    /**
     * Store a new project
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_code' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['overall_plan_progress'] = 0;
        $validated['overall_actual_progress'] = 0;

        Project::create($validated);

        return redirect()->route('admin.project.projects.index')
            ->with('success', 'Project created successfully.');
    }

    /**
     * Project Details
     */
    public function show(Project $project)
    {
        $project->load(['phases', 'phases.tasks' => function($q) {
            $q->with(['assignedTo'])->orderBy('task_order');
        }, 'createdBy']);

        // Calculate progress monitoring data
        $allTasks = $project->tasks()->with('phase')->get();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->where('status', 'completed')->count();
        $inProgressTasks = $allTasks->where('status', 'in_progress')->count();
        $onHoldTasks = $allTasks->where('status', 'on_hold')->count();

        $resolver = new TaskDependencyResolver();
        $delayedTasks = $allTasks->filter(function ($task) use ($resolver) {
            $delay = $resolver->calculateEndDateDelay($task);
            return $delay !== null && $delay > 0;
        })->count();

        // Calculate variance (plan vs actual progress)
        $overallPlanProgress = $project->overall_plan_progress;
        $overallActualProgress = $project->overall_actual_progress;
        $variance = $overallActualProgress - $overallPlanProgress;

        // Phase-level progress
        $phaseProgress = $project->phases->map(function($phase) {
            return [
                'name' => $phase->phase_name,
                'plan' => $phase->progress_plan,
                'actual' => $phase->progress_actual,
            ];
        });

        // Task status distribution
        $taskStatusDistribution = [
            'not_started' => $allTasks->where('status', 'not_started')->count(),
            'in_progress' => $inProgressTasks,
            'completed' => $completedTasks,
            'on_hold' => $onHoldTasks,
            'cancelled' => $allTasks->where('status', 'cancelled')->count(),
        ];

        // Resolve task dependencies and effective dates
        $dependencyError = null;
        try {
            $resolver = new TaskDependencyResolver();
            $effectiveDates = $resolver->resolve($project);
        } catch (\Exception $e) {
            $effectiveDates = [];
            $dependencyError = $e->getMessage();
        }

        return view('admin.project.show', compact(
            'project',
            'totalTasks',
            'completedTasks',
            'inProgressTasks',
            'delayedTasks',
            'onHoldTasks',
            'variance',
            'phaseProgress',
            'taskStatusDistribution',
            'effectiveDates',
            'dependencyError'
        ));
    }

    /**
     * Show edit project form
     */
    public function edit(Project $project)
    {
        return view('admin.project.edit', compact('project'));
    }

    /**
     * Update project
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'project_code' => 'nullable|string|max:255',
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
        ]);

        $project->update($validated);

        return redirect()->route('admin.project.projects.index')
            ->with('success', 'Project updated successfully.');
    }
}
