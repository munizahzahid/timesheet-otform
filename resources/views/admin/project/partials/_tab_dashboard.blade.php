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
                        <canvas id="taskStatusDashboardChart"></canvas>
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
                    <canvas id="phaseBarDashboardChart"></canvas>
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
<script>
    (function() {
        const chartLabels = @json($chartLabels);
        const chartData = @json($chartData);
        const chartColors = @json($chartColors);
        const phaseNames = @json($phaseNames);
        const phasePlanData = @json($phasePlanData);
        const phaseActualData = @json($phaseActualData);

        // Task Status Pie Chart
        if (chartLabels.length > 0) {
            new Chart(document.getElementById('taskStatusDashboardChart'), {
                type: 'pie',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        // Phase Progress Bar Chart
        if (phaseNames.length > 0) {
            new Chart(document.getElementById('phaseBarDashboardChart'), {
                type: 'bar',
                data: {
                    labels: phaseNames,
                    datasets: [
                        {
                            label: 'Plan',
                            data: phasePlanData,
                            backgroundColor: '#3b82f6',
                            borderRadius: 4
                        },
                        {
                            label: 'Actual',
                            data: phaseActualData,
                            backgroundColor: '#22c55e',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, max: 100 }
                    }
                }
            });
        }
    })();
</script>
@endpush
