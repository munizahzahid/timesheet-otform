<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProgressLog;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\DesknetSyncService;
use App\Services\TaskDependencyResolver;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Project Calendar
     */
    public function calendar(Request $request)
    {
        $view = in_array($request->input('view'), ['day', 'week', 'month', 'year']) ? $request->input('view') : 'month';
        $day = (int) $request->input('day', now()->day);
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $baseDate = now()->setDate($year, $month, $day)->startOfDay();

        $calendarData = match ($view) {
            'day' => $this->buildDayCalendar($baseDate),
            'week' => $this->buildWeekCalendar($baseDate),
            'month' => $this->buildMonthCalendar($baseDate),
            'year' => $this->buildYearCalendar($baseDate),
            default => $this->buildMonthCalendar($baseDate),
        };

        $tasks = $calendarData['tasks'];
        $periodStart = $calendarData['start'];
        $periodEnd = $calendarData['end'];
        $weeks = $calendarData['weeks'] ?? [];
        $months = $calendarData['months'] ?? [];
        $dayTasks = $calendarData['dayTasks'] ?? collect();
        $weekDays = $calendarData['weekDays'] ?? [];

        return view('admin.project.calendar', compact(
            'view', 'day', 'month', 'year', 'weeks', 'months', 'dayTasks', 'weekDays', 'tasks', 'periodStart', 'periodEnd'
        ));
    }

    private function fetchTasks($start, $end)
    {
        return ProjectTask::with(['project', 'phase', 'assignedTo'])
            ->whereNotNull('start_date_plan')
            ->whereNotNull('end_date_plan')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date_plan', [$start, $end])
                      ->orWhereBetween('end_date_plan', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('start_date_plan', '<=', $start)
                            ->where('end_date_plan', '>=', $end);
                      });
            })
            ->orderBy('start_date_plan')
            ->get();
    }

    private function tasksForDay($tasks, $day)
    {
        return $tasks->filter(function ($task) use ($day) {
            return $day->between(
                $task->start_date_plan->startOfDay(),
                $task->end_date_plan->endOfDay()
            );
        });
    }

    private function buildDayCalendar($baseDate)
    {
        $tasks = $this->fetchTasks($baseDate->copy()->startOfDay(), $baseDate->copy()->endOfDay());
        return [
            'start' => $baseDate->copy()->startOfDay(),
            'end' => $baseDate->copy()->endOfDay(),
            'tasks' => $tasks,
            'dayTasks' => $this->tasksForDay($tasks, $baseDate),
        ];
    }

    private function buildWeekBars($tasks, $weekStart)
    {
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $weekStartTs = $weekStart->copy()->startOfDay();
        $weekEndTs = $weekEnd->copy()->endOfDay();

        $bars = [];
        foreach ($tasks as $task) {
            if ($task->end_date_plan->startOfDay()->lt($weekStartTs) || $task->start_date_plan->startOfDay()->gt($weekEndTs)) {
                continue;
            }

            $taskStart = $task->start_date_plan->copy()->startOfDay();
            $taskEnd = $task->end_date_plan->copy()->startOfDay();
            $startCol = max(0, (int) $weekStartTs->diffInDays($taskStart, false));
            $endCol = min(6, (int) $weekStartTs->diffInDays($taskEnd, false));

            $bars[] = [
                'task' => $task,
                'start_col' => (int) $startCol,
                'end_col' => (int) $endCol,
            ];
        }

        // Assign vertical levels so overlapping bars don't stack on each other
        usort($bars, function ($a, $b) {
            if ($a['start_col'] !== $b['start_col']) {
                return $a['start_col'] <=> $b['start_col'];
            }
            return $b['end_col'] <=> $a['end_col'];
        });

        $levels = [];
        $maxLevel = -1;
        foreach ($bars as $index => $bar) {
            $level = 0;
            while (true) {
                $conflict = false;
                foreach ($levels[$level] ?? [] as $existing) {
                    if ($bar['start_col'] <= $existing['end_col'] && $bar['end_col'] >= $existing['start_col']) {
                        $conflict = true;
                        break;
                    }
                }
                if (!$conflict) {
                    break;
                }
                $level++;
            }
            $levels[$level][] = $bar;
            $bars[$index]['level'] = $level;
            $maxLevel = max($maxLevel, $level);
        }

        return [
            'bars' => $bars,
            'max_level' => $maxLevel,
        ];
    }

    private function buildWeekCalendar($baseDate)
    {
        $start = $baseDate->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $end = $start->copy()->addDays(6)->endOfDay();
        $tasks = $this->fetchTasks($start, $end);

        $weekDays = [];
        $current = $start->copy();
        while ($current <= $end) {
            $weekDays[] = [
                'date' => $current->copy(),
                'in_month' => true,
                'tasks' => $this->tasksForDay($tasks, $current),
            ];
            $current->addDay();
        }

        $barData = $this->buildWeekBars($tasks, $start);

        return [
            'start' => $start,
            'end' => $end,
            'tasks' => $tasks,
            'weekDays' => $weekDays,
            'weeks' => [[
                'days' => $weekDays,
                'bars' => $barData['bars'],
                'max_level' => $barData['max_level'],
            ]],
        ];
    }

    private function buildMonthCalendar($baseDate)
    {
        $startOfMonth = $baseDate->copy()->startOfMonth();
        $endOfMonth = $baseDate->copy()->endOfMonth();
        $tasks = $this->fetchTasks($startOfMonth, $endOfMonth);

        $calendarStart = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $calendarEnd = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $weeks = [];
        $current = $calendarStart->copy();
        while ($current <= $calendarEnd) {
            $weekDays = [];
            $weekStart = $current->copy();
            for ($i = 0; $i < 7; $i++) {
                $day = $current->copy();
                $weekDays[] = [
                    'date' => $day,
                    'in_month' => $day->month === $baseDate->month,
                    'tasks' => $this->tasksForDay($tasks, $day),
                ];
                $current->addDay();
            }
            $barData = $this->buildWeekBars($tasks, $weekStart);
            $weeks[] = [
                'days' => $weekDays,
                'bars' => $barData['bars'],
                'max_level' => $barData['max_level'],
            ];
        }

        return [
            'start' => $startOfMonth,
            'end' => $endOfMonth,
            'tasks' => $tasks,
            'weeks' => $weeks,
        ];
    }

    private function buildYearCalendar($baseDate)
    {
        $start = $baseDate->copy()->startOfYear();
        $end = $baseDate->copy()->endOfYear();
        $tasks = $this->fetchTasks($start, $end);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $baseDate->copy()->month($m)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $weeks = [];
            $calendarStart = $monthStart->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
            $calendarEnd = $monthEnd->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);
            $current = $calendarStart->copy();
            while ($current <= $calendarEnd) {
                $weekDays = [];
                $weekStart = $current->copy();
                for ($i = 0; $i < 7; $i++) {
                    $day = $current->copy();
                    $weekDays[] = [
                        'date' => $day,
                        'in_month' => $day->month === $m,
                        'tasks' => $this->tasksForDay($tasks, $day),
                    ];
                    $current->addDay();
                }
                $barData = $this->buildWeekBars($tasks, $weekStart);
                $weeks[] = [
                    'days' => $weekDays,
                    'bars' => $barData['bars'],
                    'max_level' => $barData['max_level'],
                ];
            }
            $months[] = [
                'month' => $m,
                'name' => DateTime::createFromFormat('!m', $m)->format('F'),
                'weeks' => $weeks,
            ];
        }

        return [
            'start' => $start,
            'end' => $end,
            'tasks' => $tasks,
            'months' => $months,
        ];
    }

    /**
     * Project List
     */
    public function index()
    {
        $projects = Project::orderByDesc('start_date_plan')->get();
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
            'project_manager' => 'nullable|string|max:200',
            'project_manager_staff_id' => 'nullable|string|max:50',
            'project_manager_department' => 'nullable|string|max:100',
            'deskman_1' => 'nullable|string|max:200',
            'deskman_1_staff_id' => 'nullable|string|max:50',
            'deskman_1_department' => 'nullable|string|max:100',
            'deskman_2' => 'nullable|string|max:200',
            'deskman_2_staff_id' => 'nullable|string|max:50',
            'deskman_2_department' => 'nullable|string|max:100',
            'po_no' => 'nullable|string|max:100',
            'client' => 'nullable|string|max:200',
            'attn' => 'nullable|string|max:200',
            'full_address' => 'nullable|string',
            'tin' => 'nullable|string|max:100',
            'identification_no' => 'nullable|string|max:100',
            'contact_no' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:200',
            'exemption_cert_no' => 'nullable|string|max:100',
            'term_1' => 'nullable|string|max:255',
            'term_2' => 'nullable|string|max:255',
            'term_3' => 'nullable|string|max:255',
            'term_4' => 'nullable|string|max:255',
            'term_5' => 'nullable|string|max:255',
            'project_value' => 'nullable|numeric',
            'purchasing_budget_100' => 'nullable|numeric',
            'purchasing_budget_95' => 'nullable|numeric',
            'year' => 'nullable|integer',
            'project_schedule_status' => 'nullable|string|max:100',
        ]);

        $userName = auth()->user()->name ?? 'System';

        $validated['created_by'] = auth()->id();
        $validated['overall_plan_progress'] = 0;
        $validated['overall_actual_progress'] = 0;
        $validated['date_time_added'] = now();
        $validated['added_by'] = $userName;
        $validated['date_time_updated'] = now();
        $validated['updated_by'] = $userName;

        $project = Project::create($validated);

        $pushMessage = '';
        if ($request->input('action') === 'push_to_desknet') {
            try {
                $service = new DesknetSyncService();
                $service->pushProjectToDesknet($project, auth()->id());
                $pushMessage = ' and pushed to Desknet';
            } catch (\Throwable $e) {
                Log::error('Desknet push failed on create', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
                return redirect()->route('admin.project.projects.show', $project)
                    ->with('error', 'Project created locally, but Desknet push failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.project.projects.show', $project)
            ->with('success', 'Project created successfully' . $pushMessage . '.');
    }

    /**
     * Project Details
     */
    public function show(Project $project)
    {
        $project->load(['phases', 'phases.tasks' => function($q) {
            $q->with(['assignedTo'])->orderBy('task_order');
        }, 'createdBy']);

        // Load tasks with relationships for the tasks tab
        $tasks = $project->tasks()
            ->with(['phase', 'assignedTo', 'comments.user', 'attachments.user'])
            ->withCount('comments')
            ->withCount('attachments')
            ->orderBy('task_order')
            ->get();

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

        $staffList = \App\Models\User::where('is_active', true)
            ->with('department')
            ->orderBy('name')
            ->get(['id', 'name', 'staff_no', 'department_id', 'desknet_id']);

        return view('admin.project.show', compact(
            'project',
            'tasks',
            'totalTasks',
            'completedTasks',
            'inProgressTasks',
            'delayedTasks',
            'onHoldTasks',
            'variance',
            'phaseProgress',
            'taskStatusDistribution',
            'effectiveDates',
            'dependencyError',
            'staffList'
        ));
    }

    /**
     * Show edit project form
     */
    public function edit(Project $project)
    {
        return redirect()->route('admin.project.projects.show', ['project' => $project, 'tab' => 'details', 'edit' => 1]);
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
            'project_manager' => 'nullable|string|max:200',
            'project_manager_staff_id' => 'nullable|string|max:50',
            'project_manager_department' => 'nullable|string|max:100',
            'deskman_1' => 'nullable|string|max:200',
            'deskman_1_staff_id' => 'nullable|string|max:50',
            'deskman_1_department' => 'nullable|string|max:100',
            'deskman_2' => 'nullable|string|max:200',
            'deskman_2_staff_id' => 'nullable|string|max:50',
            'deskman_2_department' => 'nullable|string|max:100',
            'po_no' => 'nullable|string|max:100',
            'client' => 'nullable|string|max:200',
            'attn' => 'nullable|string|max:200',
            'full_address' => 'nullable|string',
            'tin' => 'nullable|string|max:100',
            'identification_no' => 'nullable|string|max:100',
            'contact_no' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:200',
            'exemption_cert_no' => 'nullable|string|max:100',
            'term_1' => 'nullable|string|max:255',
            'term_2' => 'nullable|string|max:255',
            'term_3' => 'nullable|string|max:255',
            'term_4' => 'nullable|string|max:255',
            'term_5' => 'nullable|string|max:255',
            'project_value' => 'nullable|numeric',
            'purchasing_budget_100' => 'nullable|numeric',
            'purchasing_budget_95' => 'nullable|numeric',
            'year' => 'nullable|integer',
            'project_schedule_status' => 'nullable|string|max:100',
        ]);

        $validated['date_time_updated'] = now();
        $validated['updated_by'] = auth()->user()->name ?? 'System';

        $project->update($validated);
        $project->refresh();

        $pushMessage = '';
        if ($request->input('action') === 'push_to_desknet') {
            try {
                $service = new DesknetSyncService();
                $service->pushProjectToDesknet($project, auth()->id());
                $pushMessage = ' and pushed to Desknet';
            } catch (\Throwable $e) {
                Log::error('Desknet push failed on update', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
                return redirect()->route('admin.project.projects.show', ['project' => $project, 'tab' => 'details'])
                    ->with('error', 'Project saved locally, but Desknet push failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.project.projects.show', ['project' => $project, 'tab' => 'details'])
            ->with('success', 'Project updated successfully' . $pushMessage . '.');
    }

    /**
     * Show all tasks assigned to a user across all projects
     */
    public function assignedTasks(User $user)
    {
        $tasks = ProjectTask::with(['project', 'phase'])
            ->where('assigned_to', $user->id)
            ->orderBy('end_date_plan', 'asc')
            ->get();

        return view('admin.project.assigned-tasks', compact('user', 'tasks'));
    }

}
