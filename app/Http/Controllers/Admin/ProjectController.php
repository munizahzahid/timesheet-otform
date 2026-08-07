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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    /**
     * Project Executive Dashboard
     */
    public function dashboard(Request $request)
    {
        // Auto-mark projects with passed delivery date (end_date_plan before July 2026) as completed
        $cutoffDate = '2026-07-01';
        Project::where('end_date_plan', '<', $cutoffDate)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'completed',
                'updated_by' => 'System',
                'date_time_updated' => now(),
            ]);

        $totalProjects = Project::count();
        $activeProjects = Project::where('status', 'active')->count();
        $completedProjects = Project::where('status', 'completed')->count();
        $delayedProjects = Project::where('status', 'delayed')->count();

        $projects = Project::latest()->get();

        // Staff timeline: active projects with planned dates per staff (PM, deskman, or task assigned)
        $pmProjects = DB::table('pm_projects')
            ->join('users', 'pm_projects.project_manager_staff_id', '=', 'users.staff_no')
            ->whereNotNull('pm_projects.project_manager_staff_id')
            ->whereNotNull('users.staff_no')
            ->where('pm_projects.status', 'active')
            ->whereNotNull('pm_projects.start_date_plan')
            ->whereNotNull('pm_projects.end_date_plan')
            ->select('users.id as user_id', 'users.name', 'pm_projects.id as project_id', 'pm_projects.project_name', 'pm_projects.start_date_plan', 'pm_projects.end_date_plan')
            ->get();

        $deskman1Projects = DB::table('pm_projects')
            ->join('users', 'pm_projects.deskman_1_staff_id', '=', 'users.staff_no')
            ->whereNotNull('pm_projects.deskman_1_staff_id')
            ->whereNotNull('users.staff_no')
            ->where('pm_projects.status', 'active')
            ->whereNotNull('pm_projects.start_date_plan')
            ->whereNotNull('pm_projects.end_date_plan')
            ->select('users.id as user_id', 'users.name', 'pm_projects.id as project_id', 'pm_projects.project_name', 'pm_projects.start_date_plan', 'pm_projects.end_date_plan')
            ->get();

        $deskman2Projects = DB::table('pm_projects')
            ->join('users', 'pm_projects.deskman_2_staff_id', '=', 'users.staff_no')
            ->whereNotNull('pm_projects.deskman_2_staff_id')
            ->whereNotNull('users.staff_no')
            ->where('pm_projects.status', 'active')
            ->whereNotNull('pm_projects.start_date_plan')
            ->whereNotNull('pm_projects.end_date_plan')
            ->select('users.id as user_id', 'users.name', 'pm_projects.id as project_id', 'pm_projects.project_name', 'pm_projects.start_date_plan', 'pm_projects.end_date_plan')
            ->get();

        $taskProjects = DB::table('project_tasks')
            ->join('users', 'project_tasks.assigned_to', '=', 'users.id')
            ->whereNotNull('project_tasks.assigned_to')
            ->join('pm_projects', 'project_tasks.project_id', '=', 'pm_projects.id')
            ->where('pm_projects.status', 'active')
            ->whereNotNull('pm_projects.start_date_plan')
            ->whereNotNull('pm_projects.end_date_plan')
            ->select('users.id as user_id', 'users.name', 'pm_projects.id as project_id', 'pm_projects.project_name', 'pm_projects.start_date_plan', 'pm_projects.end_date_plan')
            ->get();

        $allStaffProjects = $pmProjects->concat($deskman1Projects)->concat($deskman2Projects)->concat($taskProjects);

        // Group by staff, keeping only active projects with unique project_ids
        $staffTimeline = $allStaffProjects
            ->groupBy('user_id')
            ->map(function($items) {
                $uniqueProjects = $items->unique('project_id')->values();
                return [
                    'id' => $items->first()->user_id,
                    'name' => $items->first()->name,
                    'projects' => $uniqueProjects->map(function($p) {
                        return [
                            'id' => $p->project_id,
                            'name' => $p->project_name,
                            'start_date' => $p->start_date_plan,
                            'end_date' => $p->end_date_plan,
                        ];
                    })->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        // Calculate timeline week range across all active projects
        $allDates = collect($staffTimeline)->flatMap(function($staff) {
            return collect($staff['projects'])->flatMap(function($p) {
                return [$p['start_date'], $p['end_date']];
            });
        })->filter();

        $weekLabels = [];
        $timelineStart = null;
        $weekCount = 0;
        if ($allDates->isNotEmpty()) {
            $timelineStart = \Carbon\Carbon::parse($allDates->min())->startOfWeek();
            $timelineEnd = \Carbon\Carbon::parse($allDates->max())->endOfWeek();
            $weekCount = (int) $timelineStart->diffInWeeks($timelineEnd) + 1;

            $current = $timelineStart->copy();
            for ($i = 0; $i < $weekCount; $i++) {
                $weekLabels[] = $current->copy();
                $current->addWeek();
            }

            // Calculate week offsets for each project
            foreach ($staffTimeline as $key => $staff) {
                foreach ($staff['projects'] as $pKey => $project) {
                    $pStart = \Carbon\Carbon::parse($project['start_date'])->startOfWeek();
                    $pEnd = \Carbon\Carbon::parse($project['end_date'])->endOfWeek();
                    $startWeek = (int) $timelineStart->diffInWeeks($pStart);
                    $durationWeeks = max(1, (int) $pStart->diffInWeeks($pEnd) + 1);
                    $staffTimeline[$key]['projects'][$pKey]['start_week'] = $startWeek;
                    $staffTimeline[$key]['projects'][$pKey]['duration_weeks'] = $durationWeeks;
                    // Assign consistent color based on project ID
                    $staffTimeline[$key]['projects'][$pKey]['color_index'] = $project['id'] % 6;
                }
            }
        }

        // Keep old staffInvolvement for backward compatibility (count data for stat display if needed)
        $staffInvolvement = collect($staffTimeline)->map(function($staff) {
            return [
                'id' => $staff['id'],
                'name' => $staff['name'],
                'project_count' => count($staff['projects']),
                'active_count' => count($staff['projects']),
            ];
        })->all();

        // Budget year filter (default to current year; 'all' = no filter)
        $budgetYearInput = $request->input('budget_year');
        if (is_null($budgetYearInput)) {
            $budgetYear = now()->format('Y');
        } elseif (strtolower($budgetYearInput) === 'all') {
            $budgetYear = null;
        } else {
            $budgetYear = $budgetYearInput;
        }

        $availableYears = Project::whereNotNull('year')
            ->where('year', '>=', 2025)
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->values()
            ->all();

        $budgetQuery = Project::whereNotNull('project_value')
            ->where('project_value', '>', 0);

        if ($budgetYear) {
            $budgetQuery->where('year', $budgetYear);
        }

        $budgetProjects = $budgetQuery
            ->orderByDesc('start_date_plan')
            ->get(['id', 'project_name', 'project_value', 'actual_cost', 'start_date_plan']);

        $totalBudgetPlan = $budgetQuery->clone()->sum('project_value') ?? 0;
        $totalBudgetActual = $budgetQuery->clone()->sum('actual_cost') ?? 0;
        $budgetVariance = $totalBudgetPlan - $totalBudgetActual;

        // Task status breakdown for active projects
        $taskStatusCounts = DB::table('project_tasks')
            ->join('pm_projects', 'project_tasks.project_id', '=', 'pm_projects.id')
            ->where('pm_projects.status', 'active')
            ->whereIn('project_tasks.status', ['not_started', 'in_progress', 'completed', 'on_hold', 'cancelled'])
            ->select('project_tasks.status', DB::raw('count(*) as count'))
            ->groupBy('project_tasks.status')
            ->pluck('count', 'status');

        $taskStatusData = collect([
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
        ])->map(function ($label, $status) use ($taskStatusCounts) {
            return [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($taskStatusCounts[$status] ?? 0),
            ];
        })->values()->all();

        // Task status breakdown for each active project (for stacked bar chart)
        $projectTaskStatusData = Project::where('status', 'active')
            ->withCount([
                'tasks as not_started_count' => fn ($q) => $q->where('status', 'not_started'),
                'tasks as in_progress_count' => fn ($q) => $q->where('status', 'in_progress'),
                'tasks as completed_count' => fn ($q) => $q->where('status', 'completed'),
                'tasks as on_hold_count' => fn ($q) => $q->where('status', 'on_hold'),
                'tasks as cancelled_count' => fn ($q) => $q->where('status', 'cancelled'),
            ])
            ->get(['id', 'project_name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'project_name' => $p->project_name,
                'not_started' => $p->not_started_count,
                'in_progress' => $p->in_progress_count,
                'completed' => $p->completed_count,
                'on_hold' => $p->on_hold_count,
                'cancelled' => $p->cancelled_count,
                'total' => $p->not_started_count + $p->in_progress_count + $p->completed_count + $p->on_hold_count + $p->cancelled_count,
            ])
            ->where('total', '>', 0)
            ->values()
            ->all();

        return view('admin.project.dashboard', compact(
            'totalProjects',
            'activeProjects',
            'completedProjects',
            'delayedProjects',
            'projects',
            'staffInvolvement',
            'staffTimeline',
            'weekLabels',
            'weekCount',
            'budgetProjects',
            'totalBudgetPlan',
            'totalBudgetActual',
            'budgetVariance',
            'budgetYear',
            'availableYears',
            'taskStatusData',
            'projectTaskStatusData'
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
        $projects = Project::where('year', '>=', 2025)
            ->orderByDesc('start_date_plan')
            ->get();
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
            'actual_cost' => 'nullable|numeric',
            'year' => 'nullable|integer',
            'project_schedule_status' => 'nullable|string|max:100',
        ]);

        $userName = auth()->user()->name ?? 'System';

        if (empty($validated['year']) && !empty($validated['start_date_plan'])) {
            $validated['year'] = (int) date('Y', strtotime($validated['start_date_plan']));
        }

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
        $redirect = request('redirect');
        $params = ['project' => $project, 'tab' => 'details', 'edit' => 1];
        if ($redirect) {
            $params['redirect'] = $redirect;
        }
        return redirect()->route('admin.project.projects.show', $params);
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
            'actual_cost' => 'nullable|numeric',
            'year' => 'nullable|integer',
            'project_schedule_status' => 'nullable|string|max:100',
        ]);

        if (empty($validated['year']) && !empty($validated['start_date_plan'])) {
            $validated['year'] = (int) date('Y', strtotime($validated['start_date_plan']));
        }

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
                $redirect = $request->input('redirect');
                if ($redirect) {
                    return redirect($redirect)->with('error', 'Project saved locally, but Desknet push failed: ' . $e->getMessage());
                }
                return redirect()->route('admin.project.projects.show', ['project' => $project, 'tab' => 'details'])
                    ->with('error', 'Project saved locally, but Desknet push failed: ' . $e->getMessage());
            }
        }

        $redirect = $request->input('redirect');
        if ($redirect) {
            return redirect($redirect)->with('success', 'Project updated successfully' . $pushMessage . '.');
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

    /**
     * Show projects a staff is involved in (PM, deskman, or task assigned)
     */
    public function staffInvolvement(User $user)
    {
        $projects = Project::query()
            ->where(function ($query) use ($user) {
                $query->where('project_manager_staff_id', $user->staff_no)
                    ->orWhere('deskman_1_staff_id', $user->staff_no)
                    ->orWhere('deskman_2_staff_id', $user->staff_no);
            })
            ->orWhereHas('tasks', function ($query) use ($user) {
                $query->where('assigned_to', $user->id);
            })
            ->with(['tasks' => function ($query) use ($user) {
                $query->where('assigned_to', $user->id);
            }])
            ->get();

        // Sort projects: active first, then delayed, then completed
        $projects = $projects->sortByDesc(function (Project $project) {
            $order = ['active' => 3, 'delayed' => 2, 'completed' => 1];
            return $order[$project->status] ?? 0;
        })->values();

        $projectData = $projects->map(function (Project $project) use ($user) {
            $roles = [];

            if ($project->project_manager_staff_id == $user->staff_no) {
                $roles[] = 'Project Manager';
            }
            if ($project->deskman_1_staff_id == $user->staff_no) {
                $roles[] = 'Deskman 1';
            }
            if ($project->deskman_2_staff_id == $user->staff_no) {
                $roles[] = 'Deskman 2';
            }
            if ($project->tasks->isNotEmpty()) {
                $roles[] = 'Task Assigned';
            }

            // Sort tasks: active first (in_progress, not_started, on_hold), then completed, then cancelled
            $taskStatusOrder = ['in_progress' => 3, 'not_started' => 2, 'on_hold' => 1, 'completed' => 0, 'cancelled' => -1];
            $sortedTasks = $project->tasks->sortByDesc(function ($task) use ($taskStatusOrder) {
                return $taskStatusOrder[$task->status] ?? 0;
            })->values();

            return [
                'project' => $project,
                'roles' => array_unique($roles),
                'tasks' => $sortedTasks,
            ];
        });

        return view('admin.project.staff_involvement', compact('user', 'projectData'));
    }

}
