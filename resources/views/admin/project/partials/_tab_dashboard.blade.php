@php
    $colorMap = [
        'not_started' => '#9ca3af',
        'in_progress' => '#3b82f6',
        'completed' => '#22c55e',
        'on_hold' => '#eab308',
        'cancelled' => '#6b7280',
    ];
    $bgClassMap = [
        'not_started' => 'bg-gray-200',
        'in_progress' => 'bg-blue-100',
        'completed' => 'bg-green-100',
        'on_hold' => 'bg-yellow-100',
        'cancelled' => 'bg-gray-100',
    ];

    $tabDashboardProps = [
        'overallActualProgress' => $project->overall_actual_progress,
        'overallPlanProgress' => $project->overall_plan_progress,
        'variance' => $variance,
        'delayedTasks' => $delayedTasks,
        'totalTasks' => $totalTasks,
        'daysBehind' => $daysBehind ?? 0,
        'taskStatusDistribution' => collect($taskStatusDistribution)->map(function ($count, $status) use ($colorMap, $bgClassMap) {
            return [
                'status' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'count' => $count,
                'color' => $colorMap[$status] ?? '#9ca3af',
                'bgClass' => $bgClassMap[$status] ?? 'bg-gray-50',
            ];
        })->values()->toArray(),
        'phaseProgress' => $phaseProgress->toArray(),
    ];
@endphp

<script type="application/json" id="project-tab-dashboard-props">@json($tabDashboardProps)</script>
<div id="project-tab-dashboard"></div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
@endpush
