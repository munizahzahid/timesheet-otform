<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl px-6 py-5 text-white shadow-lg" style="background: linear-gradient(90deg, #4F46E5, #2563EB, #3B82F6);">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl">Project Executive Dashboard</h2>
                    <p class="text-blue-100 text-sm mt-1">Overview of projects, budget, and staff allocation</p>
                </div>
                <span class="text-xs bg-white/20 rounded-full px-3 py-1.5 whitespace-nowrap">Refreshed {{ now()->format('M d, Y h:i A') }}</span>
            </div>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    @php
    $dashboardProps = [
        'totalProjects' => $totalProjects,
        'activeProjects' => $activeProjects,
        'completedProjects' => $completedProjects,
        'delayedProjects' => $delayedProjects,
        'staffTimeline' => collect($staffTimeline)->map(function ($staff) {
            return [
                'name' => $staff['name'],
                'projects' => collect($staff['projects'])->map(function ($project) {
                    return [
                        'id' => $project['id'],
                        'name' => $project['name'],
                        'url' => route('admin.project.projects.show', $project['id']),
                        'start_week' => $project['start_week'],
                        'duration_weeks' => $project['duration_weeks'],
                        'color_index' => $project['color_index'] ?? 0,
                        'start_date' => \Carbon\Carbon::parse($project['start_date'])->format('d M Y'),
                        'end_date' => \Carbon\Carbon::parse($project['end_date'])->format('d M Y'),
                    ];
                })->toArray(),
            ];
        })->toArray(),
        'weekCount' => $weekCount,
        'weekLabels' => collect($weekLabels)->map(fn ($week) => [
            'date' => $week->format('M d'),
            'isCurrentWeek' => $week->isCurrentWeek(),
        ])->toArray(),
        'totalBudgetPlan' => $totalBudgetPlan,
        'totalBudgetActual' => $totalBudgetActual,
        'budgetVariance' => $budgetVariance,
        'budgetYear' => $budgetYear,
        'availableYears' => $availableYears,
        'budgetProjects' => $budgetProjects->map(fn ($p) => [
            'project_name' => $p->project_name,
            'project_value' => $p->project_value,
            'actual_cost' => $p->actual_cost,
        ])->values()->toArray(),
        'progressProjects' => $projects->where('status', 'active')->sortByDesc('start_date_plan')->values()->map(fn ($p) => [
            'project_name' => $p->project_name,
            'overall_plan_progress' => $p->overall_plan_progress,
            'overall_actual_progress' => $p->overall_actual_progress,
        ])->toArray(),
        'taskStatusData' => collect($taskStatusData)->map(fn ($s) => [
            'label' => $s['label'],
            'count' => $s['count'],
        ])->values()->toArray(),
        'projectTaskStatusData' => collect($projectTaskStatusData)->map(fn ($p) => [
            'project_name' => $p['project_name'],
            'not_started' => $p['not_started'],
            'in_progress' => $p['in_progress'],
            'completed' => $p['completed'],
            'on_hold' => $p['on_hold'],
            'cancelled' => $p['cancelled'],
        ])->values()->toArray(),
        'dashboardUrl' => route('admin.project.dashboard'),
    ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <script type="application/json" id="project-dashboard-props">@json($dashboardProps)</script>
        <div id="project-dashboard"></div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    @endpush
</x-app-layout>
