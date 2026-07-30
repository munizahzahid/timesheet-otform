<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Executive Dashboard</h2>
            <span class="text-sm text-gray-500">Data refreshed at {{ now()->format('M d, Y h:i A') }}</span>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

        {{-- Stats Cards Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-0.5">Total Projects</p>
                <p class="text-xl font-bold text-gray-900">{{ $totalProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-0.5">Active Projects</p>
                <p class="text-xl font-bold text-green-600">{{ $activeProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-0.5">Completed Projects</p>
                <p class="text-xl font-bold text-blue-600">{{ $completedProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-0.5">Delayed Projects</p>
                <p class="text-xl font-bold text-red-600">{{ $delayedProjects }}</p>
            </div>
        </div>

        {{-- Row 2: Staff Involvement (left) + Progress Summary (right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Staff Project Timeline --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Staff Project Timeline (Active)</h3>
                @if(count($staffTimeline) > 0 && $weekCount > 0)
                    <div class="overflow-x-auto">
                        <div class="min-w-max">
                            {{-- Header --}}
                            <div class="flex border-b bg-gray-50 sticky top-0">
                                <div class="w-48 flex-shrink-0 p-3 font-semibold text-sm sticky left-0 bg-gray-50 z-10">Staff</div>
                                <div class="flex">
                                    @foreach($weekLabels as $week)
                                        <div class="flex-shrink-0 w-16 text-center text-[10px] py-2 border-l border-gray-200 {{ $week->isCurrentWeek() ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-500' }}">
                                            {{ $week->format('M d') }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Staff rows --}}
                            @foreach($staffTimeline as $staff)
                                <div class="flex border-b-2 border-gray-200 hover:bg-gray-50">
                                    <div class="w-48 flex-shrink-0 p-3 text-sm font-medium sticky left-0 bg-white z-10 border-r border-gray-200">
                                        {{ $staff['name'] }}
                                    </div>
                                    <div class="flex relative" style="width: {{ $weekCount * 64 }}px; min-height: {{ max(40, count($staff['projects']) * 24 + 8) }}px;">
                                        {{-- Week grid lines --}}
                                        @for($i = 0; $i < $weekCount; $i++)
                                            <div class="absolute top-0 bottom-0 border-l border-gray-100" style="left: {{ $i * 64 }}px; width: 64px;"></div>
                                        @endfor
                                        {{-- Project bars --}}
                                        @foreach($staff['projects'] as $project)
                                            <a href="{{ route('admin.project.projects.show', $project['id']) }}"
                                               class="absolute h-5 rounded text-white text-[10px] flex items-center px-1 overflow-hidden whitespace-nowrap shadow-sm transition hover:opacity-80 hover:shadow"
                                               style="left: {{ $project['start_week'] * 64 + 2 }}px; width: {{ max(60, $project['duration_weeks'] * 64 - 4) }}px; top: {{ 4 + $loop->index * 24 }}px; background-color: {{ ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'][$project['color_index'] ?? 0] }};"
                                               title="{{ $project['name'] }}: {{ \Carbon\Carbon::parse($project['start_date'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($project['end_date'])->format('d M Y') }}">
                                                {{ $project['name'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="text-sm text-gray-400 mt-2">No staff with active projects.</p>
                    </div>
                @endif
            </div>

            {{-- Progress Summary (Active Projects Only) --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Progress Summary (Active Projects)</h3>
                @if($projects->where('status', 'active')->count() > 0)
                    <div style="height: 320px;">
                        <canvas id="progressSummaryChart"></canvas>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-sm text-gray-400 mt-2">No active projects to display progress for.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Budget Variance Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Total Budget (Plan)</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totalBudgetPlan, 2) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Total Actual Cost</p>
                <p class="text-2xl font-bold text-green-600">{{ number_format($totalBudgetActual, 2) }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Variance</p>
                <p class="text-2xl font-bold {{ $budgetVariance >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                    {{ $budgetVariance >= 0 ? '+' : '' }}{{ number_format($budgetVariance, 2) }}
                </p>
            </div>
        </div>

        {{-- Budget Plan vs Actual Chart --}}
        <div class="bg-white border border-gray-200 rounded-lg p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Budget Plan vs Actual</h3>
                <form method="GET" action="{{ route('admin.project.dashboard') }}" class="flex items-center gap-2">
                    <label for="budget_year" class="text-xs text-gray-500">Year:</label>
                    <select name="budget_year" id="budget_year" onchange="this.form.submit()"
                            class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Years</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ $budgetYear == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @if($budgetProjects->count() > 0)
                <div style="height: 320px;">
                    <canvas id="budgetVarianceChart"></canvas>
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400 mt-2">No budget data available for the selected year.</p>
                </div>
            @endif
        </div>

    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Progress Summary (Active Projects) Chart
            const activeProjectsData = @json($projects->where('status', 'active')->values());
            if (activeProjectsData.length > 0) {
                const progressLabels = activeProjectsData.map(p => {
                    const name = p.project_name;
                    return name.length > 25 ? name.substring(0, 25) + '...' : name;
                });
                const planProgress = activeProjectsData.map(p => p.overall_plan_progress || 0);
                const actualProgress = activeProjectsData.map(p => p.overall_actual_progress || 0);

                new Chart(document.getElementById('progressSummaryChart'), {
                    type: 'bar',
                    data: {
                        labels: progressLabels,
                        datasets: [
                            {
                                label: 'Plan Progress',
                                data: planProgress,
                                backgroundColor: '#93C5FD',
                                borderRadius: 4,
                            },
                            {
                                label: 'Actual Progress',
                                data: actualProgress,
                                backgroundColor: '#22C55E',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    font: { size: 11 }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Progress (%)'
                                }
                            },
                            y: {
                                ticks: {
                                    autoSkip: false
                                }
                            }
                        }
                    }
                });
            }

            // Budget Plan vs Actual Chart
            const budgetProjectsData = @json($budgetProjects);
            if (budgetProjectsData.length > 0) {
                const budgetLabels = budgetProjectsData.map(p => {
                    const name = p.project_name;
                    return name.length > 25 ? name.substring(0, 25) + '...' : name;
                });
                const budgetPlan = budgetProjectsData.map(p => parseFloat(p.project_value) || 0);
                const budgetActual = budgetProjectsData.map(p => parseFloat(p.actual_cost) || 0);
                const budgetVarianceArr = budgetPlan.map((plan, i) => plan - budgetActual[i]);

                new Chart(document.getElementById('budgetVarianceChart'), {
                    type: 'bar',
                    data: {
                        labels: budgetLabels,
                        datasets: [
                            {
                                label: 'Budget (Plan)',
                                data: budgetPlan,
                                backgroundColor: '#3B82F6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Actual Cost',
                                data: budgetActual,
                                backgroundColor: '#10B981',
                                borderRadius: 4,
                            },
                            {
                                label: 'Variance',
                                data: budgetVarianceArr,
                                backgroundColor: budgetVarianceArr.map(v => v >= 0 ? '#6366F1' : '#EF4444'),
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Amount'
                                }
                            },
                            x: {
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
