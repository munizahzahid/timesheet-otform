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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Total Projects</p>
                <p class="text-3xl font-bold text-gray-900">{{ $totalProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Active Projects</p>
                <p class="text-3xl font-bold text-green-600">{{ $activeProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Completed Projects</p>
                <p class="text-3xl font-bold text-blue-600">{{ $completedProjects }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <p class="text-sm text-gray-500 mb-1">Delayed Projects</p>
                <p class="text-3xl font-bold text-red-600">{{ $delayedProjects }}</p>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            {{-- Project Status Overview --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 lg:col-span-1">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Project Status Overview</h3>
                @if($totalProjects > 0)
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Active</span>
                                <span class="font-medium text-gray-900">{{ $activeProjects }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Completed</span>
                                <span class="font-medium text-gray-900">{{ $completedProjects }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3">
                                <div class="bg-blue-500 h-3 rounded-full" style="width: {{ $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Delayed</span>
                                <span class="font-medium text-gray-900">{{ $delayedProjects }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3">
                                <div class="bg-red-500 h-3 rounded-full" style="width: {{ $totalProjects > 0 ? round(($delayedProjects / $totalProjects) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400">No projects yet.</p>
                @endif
            </div>

            {{-- Progress Summary Across Projects --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Progress Summary</h3>
                @if($projects->count() > 0)
                    <div class="space-y-3">
                        @foreach($projects->take(6) as $project)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 font-medium truncate max-w-[60%]">{{ $project->project_name }}</span>
                                    <div class="flex gap-4 text-xs">
                                        <span class="text-blue-600">Plan: {{ $project->overall_plan_progress }}%</span>
                                        <span class="text-green-600">Actual: {{ $project->overall_actual_progress }}%</span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5 relative">
                                    <div class="bg-blue-200 h-2.5 rounded-full absolute top-0 left-0" style="width: {{ $project->overall_plan_progress }}%"></div>
                                    <div class="bg-green-500 h-2.5 rounded-full absolute top-0 left-0" style="width: {{ $project->overall_actual_progress }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">No projects to display progress for.</p>
                @endif
            </div>
        </div>

        {{-- Staff Project Involvement --}}
        <div class="bg-white border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Staff Project Involvement</h3>
            @if(count($staffInvolvement) > 0)
                <div style="height: 320px;">
                    <canvas id="staffInvolvementChart"></canvas>
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400 mt-2">No staff project assignments yet.</p>
                </div>
            @endif
        </div>

        {{-- Recent Project Updates --}}
        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recent Project Updates</h3>
            </div>
            <div class="p-5">
                @if($recentLogs->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentLogs as $log)
                            <div class="flex items-start gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-800">
                                        <span class="font-medium">{{ $log->project->project_name ?? 'Unknown' }}</span>
                                        — {{ $log->log_type }}: {{ $log->field_name }}
                                        @if($log->new_value)
                                            → {{ $log->new_value }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ $log->changedBy->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="text-sm text-gray-400 mt-2">No recent updates. Activity will appear here as projects are updated.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const staffInvolvementData = @json($staffInvolvement);
            const labels = staffInvolvementData.map(d => d.name);
            const data = staffInvolvementData.map(d => d.project_count);

            const staffInvolvementUrlTemplate = "{{ route('admin.project.staff-involvement', ['user' => '__USER_ID__']) }}";

            new Chart(document.getElementById('staffInvolvementChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Projects Involved',
                        data: data,
                        backgroundColor: '#3B82F6',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: function(event, chartElements) {
                        event.native.target.style.cursor = chartElements.length ? 'pointer' : 'default';
                    },
                    onClick: function(event, chartElements) {
                        if (chartElements.length > 0) {
                            const index = chartElements[0].index;
                            const userId = staffInvolvementData[index].id;
                            if (userId) {
                                window.location.href = staffInvolvementUrlTemplate.replace('__USER_ID__', userId);
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' project' + (context.parsed.y !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Number of Projects'
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
        });
    </script>
    @endpush
</x-app-layout>
