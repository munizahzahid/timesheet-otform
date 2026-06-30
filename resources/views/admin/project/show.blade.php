<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Details — {{ $project->project_name }}</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.project.projects.tasks.index', $project) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    All Tasks
                </a>
                <a href="{{ route('admin.project.projects.edit', $project) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    Edit Project
                </a>
            </div>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

        {{-- Back Button --}}
        <div class="mb-6">
            <a href="{{ route('admin.project.projects.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to List
            </a>
        </div>

        {{-- Project Summary Section --}}
        <div class="bg-white border border-gray-200 rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Project Summary</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Left: Basic Info --}}
                    <div class="lg:col-span-2 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Project Name</p>
                                <p class="text-sm font-medium text-gray-900">{{ $project->project_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Project Code</p>
                                <p class="text-sm text-gray-700">{{ $project->project_code ?? '—' }}</p>
                            </div>
                        </div>

                        @if($project->description)
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Description</p>
                                <p class="text-sm text-gray-700">{{ $project->description }}</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Status</p>
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-blue-100 text-blue-800',
                                        'delayed' => 'bg-red-100 text-red-800',
                                        'on_hold' => 'bg-yellow-100 text-yellow-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $color = $statusColors[$project->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status ?? 'Not Set')) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Created By</p>
                                <p class="text-sm text-gray-700">{{ $project->createdBy->name ?? 'System' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Progress Stats --}}
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Overall Progress</h4>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-600">Plan Progress</span>
                                    <span class="font-medium text-gray-900">{{ $project->overall_plan_progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="h-3 rounded-full" style="width: {{ $project->overall_plan_progress }}%; background-color: #3b82f6;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-600">Actual Progress</span>
                                    <span class="font-medium text-gray-900">{{ $project->overall_actual_progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="h-3 rounded-full" style="width: {{ $project->overall_actual_progress }}%; background-color: #22c55e;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Date Information --}}
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Timeline</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Planned</p>
                            <div class="space-y-1">
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">Start:</span>
                                    {{ $project->start_date_plan ? $project->start_date_plan->format('d M Y') : '—' }}
                                </p>
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">End:</span>
                                    {{ $project->end_date_plan ? $project->end_date_plan->format('d M Y') : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Actual</p>
                            <div class="space-y-1">
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">Start:</span>
                                    {{ $project->start_date_actual ? $project->start_date_actual->format('d M Y') : '—' }}
                                </p>
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">End:</span>
                                    {{ $project->end_date_actual ? $project->end_date_actual->format('d M Y') : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Revised</p>
                            <div class="space-y-1">
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">Start:</span>
                                    {{ $project->start_date_revise ? $project->start_date_revise->format('d M Y') : '—' }}
                                </p>
                                <p class="text-sm text-gray-700">
                                    <span class="text-xs text-gray-500">End:</span>
                                    {{ $project->end_date_revise ? $project->end_date_revise->format('d M Y') : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Phases Summary --}}
                @if($project->phases->count() > 0)
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Phases ({{ $project->phases->count() }})</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($project->phases as $phase)
                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium text-gray-900">{{ $phase->phase_name }}</p>
                                        <span class="text-xs text-gray-500">#{{ $phase->phase_order }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                            <div class="h-2.5 rounded-full" style="width: {{ $phase->progress_actual }}%; background-color: #22c55e;"></div>
                                        </div>
                                        <span class="text-xs text-gray-600">{{ $phase->progress_actual }}%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Project Schedule / Gantt Chart Section --}}
        @include('admin.project.partials._gantt', ['effectiveDates' => $effectiveDates])

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
            $chartLabels = [];
            $chartData = [];
            $chartColors = [];
            foreach ($taskStatusDistribution as $status => $count) {
                if ($count > 0) {
                    $chartLabels[] = ucfirst(str_replace('_', ' ', $status));
                    $chartData[] = $count;
                    $chartColors[] = $colorMap[$status] ?? '#9ca3af';
                }
            }
            $phaseNames = $phaseProgress->pluck('name')->toArray();
            $phasePlanData = $phaseProgress->pluck('plan')->toArray();
            $phaseActualData = $phaseProgress->pluck('actual')->toArray();
        @endphp

        {{-- Progress Monitoring Section --}}
        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Progress Monitoring</h3>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-gray-500 text-xs">Overall Progress</p>
                    </div>
                    <div class="relative w-12 h-12">
                        <svg class="w-12 h-12 transform -rotate-90">
                            <circle cx="24" cy="24" r="20" stroke="#e5e7eb" stroke-width="4" fill="none"/>
                            <circle cx="24" cy="24" r="20" stroke="#3b82f6" stroke-width="4" fill="none"
                                    stroke-dasharray="{{ $project->overall_actual_progress / 100 * 125.6 }} 125.6"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-gray-800 text-xs font-bold">{{ $project->overall_actual_progress }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6">
                {{-- Row 1: Pie Chart + Task Status Table + Variance --}}
                <div class="flex justify-between gap-5 mb-6">
                    {{-- Task Status Pie Chart --}}
                    <div class="flex-shrink-0">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-3">Task Status %</h4>
                        <div class="flex items-center gap-4">
                            <div style="width: 150px; height: 150px;">
                                <canvas id="taskStatusChart"></canvas>
                            </div>
                            <div class="flex flex-col gap-1.5 text-xs">
                                @foreach($taskStatusDistribution as $status => $count)
                                    @if($count > 0)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-3 h-3 rounded-sm" style="background: {{ $colorMap[$status] ?? '#9ca3af' }};"></div>
                                            <span class="text-gray-600 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="w-px bg-gray-200 self-stretch"></div>

                    {{-- Task Status Table --}}
                    <div class="w-72 flex-shrink-0">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-3">Task Status %</h4>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-300">
                                    <th class="text-left py-1.5 px-2 font-bold text-gray-700 uppercase">Status</th>
                                    <th class="text-center py-1.5 px-2 font-bold text-gray-700 uppercase">Count</th>
                                    <th class="text-center py-1.5 px-2 font-bold text-gray-700 uppercase">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($taskStatusDistribution as $status => $count)
                                    <tr class="{{ $bgClassMap[$status] ?? 'bg-gray-50' }}">
                                        <td class="py-1.5 px-2 font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                        <td class="py-1.5 px-2 text-center text-gray-700">{{ $count }}</td>
                                        <td class="py-1.5 px-2 text-center text-gray-700">{{ $totalTasks > 0 ? round($count / $totalTasks * 100) : 0 }}%</td>
                                    </tr>
                                @endforeach
                                <tr class="border-t-2 border-gray-400 font-bold">
                                    <td class="py-1.5 px-2 text-gray-800">Total</td>
                                    <td class="py-1.5 px-2 text-center text-gray-800">{{ $totalTasks }}</td>
                                    <td class="py-1.5 px-2 text-center text-gray-800">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="w-px bg-gray-200 self-stretch"></div>

                    {{-- Variance & Overdue --}}
                    <div class="flex-shrink-0 w-36 space-y-4">
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 text-center">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Variance</h4>
                            <span class="text-2xl font-bold {{ $variance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $variance >= 0 ? '+' : '' }}{{ $variance }}%
                            </span>
                            <p class="text-xs text-gray-500 mt-1">{{ $variance >= 0 ? 'Ahead of plan' : 'Behind plan' }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 text-center">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Overdue</h4>
                            <span class="text-2xl font-bold {{ $delayedTasks > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $delayedTasks }}</span>
                            <p class="text-xs text-gray-500 mt-1">{{ $delayedTasks > 0 ? 'Need attention' : 'All on track' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Phase Progress Bar Chart --}}
                @if($phaseProgress->count() > 0)
                    <div class="border-t border-gray-200 pt-5">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-3">Phase Progress (Plan vs Actual)</h4>
                        <div class="h-28">
                            <canvas id="phaseBarChart"></canvas>
                        </div>
                    </div>
                @endif

                {{-- Row 3: Phase Timeline --}}
                @if($phaseProgress->count() > 0)
                    <div class="border-t border-gray-200 pt-5 mt-5">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-3">Phase Timeline</h4>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                            <div class="bg-blue-500 h-2.5 rounded-full transition-all" style="width: {{ $project->overall_actual_progress }}%"></div>
                        </div>
                        <div class="flex items-start justify-between">
                            @foreach($phaseProgress as $phase)
                                <div class="flex flex-col items-center text-center">
                                    <span class="text-gray-700 text-xs font-medium mb-1.5">{{ $phase['name'] }}</span>
                                    @if($phase['actual'] >= 100)
                                        <div class="w-7 h-7 rounded-full bg-green-500 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    @elseif($phase['actual'] > 0)
                                        <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center">
                                            <span class="text-white text-[9px] font-bold">{{ $phase['actual'] }}%</span>
                                        </div>
                                    @else
                                        <div class="w-7 h-7 rounded-full bg-gray-300 flex items-center justify-center">
                                            <div class="w-2.5 h-2.5 rounded-full bg-gray-400"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Task Status Pie Chart
                    const statusCtx = document.getElementById('taskStatusChart');
                    if (statusCtx) {
                        new Chart(statusCtx, {
                            type: 'pie',
                            data: {
                                labels: @json($chartLabels),
                                datasets: [{
                                    data: @json($chartData),
                                    backgroundColor: @json($chartColors),
                                    borderWidth: 1,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const value = context.parsed;
                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                return context.label + ': ' + value + ' (' + ((value/total)*100).toFixed(0) + '%)';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Phase Progress Bar Chart
                    const phaseCtx = document.getElementById('phaseBarChart');
                    if (phaseCtx) {
                        new Chart(phaseCtx, {
                            type: 'bar',
                            data: {
                                labels: @json($phaseNames),
                                datasets: [
                                    {
                                        label: 'Plan',
                                        data: @json($phasePlanData),
                                        backgroundColor: '#3b82f6',
                                        borderRadius: 3,
                                        barPercentage: 0.5,
                                        categoryPercentage: 0.6,
                                    },
                                    {
                                        label: 'Actual',
                                        data: @json($phaseActualData),
                                        backgroundColor: '#22c55e',
                                        borderRadius: 3,
                                        barPercentage: 0.5,
                                        categoryPercentage: 0.6,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: {
                                        ticks: { color: '#6b7280', font: { size: 10 } },
                                        grid: { display: false }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        max: 100,
                                        ticks: { color: '#6b7280', font: { size: 10 }, callback: v => v + '%' },
                                        grid: { color: '#e5e7eb' }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        labels: { color: '#374151', boxWidth: 10, font: { size: 10 } }
                                    }
                                }
                            }
                        });
                    }
                });
            </script>
        @endpush
    </div>
</x-app-layout>
