<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-4">
            <div class="p-4">
                <h3 class="text-base font-medium text-gray-900">Welcome, {{ Auth::user()->name }}</h3>
                <p class="text-sm text-gray-500">
                    Role: <span class="font-medium">{{ str_replace('_', ' ', ucfirst(Auth::user()->role)) }}</span>
                    @if(Auth::user()->department)
                        | Department: <span class="font-medium">{{ Auth::user()->department->name }}</span>
                    @endif
                </p>
            </div>
        </div>

        @if(Auth::user()->isAdmin())
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <a href="{{ route('admin.users.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-4 hover:shadow-md transition">
                    <h4 class="text-xs font-medium text-gray-500 uppercase">Users</h4>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ \App\Models\User::where('is_active', true)->count() }}</p>
                    <p class="text-xs text-gray-400">Active users</p>
                </a>
                <a href="{{ route('admin.project-codes.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-4 hover:shadow-md transition">
                    <h4 class="text-xs font-medium text-gray-500 uppercase">Project Codes</h4>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ \App\Models\ProjectCode::where('is_active', true)->count() }}</p>
                    <p class="text-xs text-gray-400">Active projects</p>
                </a>
                <a href="{{ route('admin.desknet-sync.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-4 hover:shadow-md transition">
                    <h4 class="text-xs font-medium text-gray-500 uppercase">Last Sync</h4>
                    @php $lastSync = \App\Models\DesknetSyncLog::where('status','success')->orderByDesc('completed_at')->first(); @endphp
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ $lastSync ? $lastSync->completed_at->diffForHumans() : 'Never' }}</p>
                    <p class="text-xs text-gray-400">Desknet sync status</p>
                </a>
            </div>
        @endif

        @if($canApproveTimesheets || $canApproveOtForms)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @if($canApproveTimesheets)
                    <a href="{{ route('approvals.timesheets.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-4 hover:shadow-md transition">
                        <h4 class="text-xs font-medium text-gray-500 uppercase">Pending Timesheet Approvals</h4>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ $pendingTimesheetApprovalCount }}</p>
                        <p class="text-xs text-gray-400">Awaiting your approval</p>
                    </a>
                @endif
                @if($canApproveOtForms)
                    <a href="{{ route('approvals.ot-forms.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-4 hover:shadow-md transition">
                        <h4 class="text-xs font-medium text-gray-500 uppercase">Pending OT Approvals</h4>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ $pendingOtApprovalCount }}</p>
                        <p class="text-xs text-gray-400">Awaiting your approval</p>
                    </a>
                @endif
            </div>
        @endif

        {{-- Active Training Sessions --}}
        @if($activeTrainingSessions->isNotEmpty())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-4">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Training Sessions</h4>
                        <p class="text-xs text-gray-400">Active training sessions available for attendance</p>
                    </div>
                    <a href="{{ route('training-attendance.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View All</a>
                </div>
                <div class="p-4 space-y-3">
                    @foreach($activeTrainingSessions as $session)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">{{ $session->name }}</span>
                                    @if($session->is_active)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $session->training_date->format('d M Y') }} &middot;
                                    {{ $session->time_in->format('H:i') }} - {{ $session->time_out->format('H:i') }} &middot;
                                    {{ $session->venue }}
                                </p>
                            </div>
                            <div>
                                @if($session->is_active && ! $session->attended)
                                    <a href="{{ route('training-attendance.index') }}" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs font-medium hover:bg-indigo-700">Mark Attendance</a>
                                @elseif($session->attended)
                                    <span class="px-3 py-1.5 bg-green-100 text-green-800 rounded text-xs font-medium">Attended</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- OT Analytics Filter --}}
        @if($availableMonths->isNotEmpty())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-4">
                <div class="p-4">
                    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <label for="month" class="text-xs font-medium text-gray-700">Filter OT charts by month:</label>
                        <select id="month" name="month" onchange="this.form.submit()" class="block w-full sm:w-56 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 text-xs">
                            @foreach($availableMonths as $m)
                                <option value="{{ $m->value }}" {{ $selectedMonth === $m->value ? 'selected' : '' }}>{{ $m->label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            {{-- OT Analytics Charts --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                {{-- OT Hours by Project Donut Chart --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg lg:row-span-2">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">OT Hours by Project</h4>
                        <p class="text-xs text-gray-400">{{ date('F Y', mktime(0, 0, 0, $selectedMonthNumber, 1, $selectedYear)) }}</p>
                    </div>
                    <div class="p-4">
                        <div class="h-64 lg:h-96 flex items-center justify-center">
                            <canvas id="projectOtChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Monthly OT Hours Bar Chart --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Total OT Hours by Month</h4>
                        <p class="text-xs text-gray-400">All approved OT forms</p>
                    </div>
                    <div class="p-4">
                        <div class="h-44">
                            <canvas id="monthlyOtChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- OT Hours by Staff Bar Chart --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">OT Hours by Staff</h4>
                        <p class="text-xs text-gray-400">{{ date('F Y', mktime(0, 0, 0, $selectedMonthNumber, 1, $selectedYear)) }}</p>
                    </div>
                    <div class="p-4">
                        <div class="h-44">
                            <canvas id="staffOtChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Recent Actions --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Recent Actions</h4>
                    <p class="text-xs text-gray-400">Your recent Timesheet / OT Form activity</p>
                </div>
                <div class="p-4">
                    @if($recentActions->isEmpty())
                        <p class="text-xs text-gray-500">No recent actions.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($recentActions as $action)
                                <li class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-900">
                                            @if($action->model_type === \App\Models\Timesheet::class)
                                                Timesheet
                                            @else
                                                OT Form
                                            @endif
                                            <span class="text-gray-500">{{ $action->action }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $action->description }} &middot; {{ $action->created_at->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Recent Updates --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Recent Updates</h4>
                    <p class="text-xs text-gray-400">Status changes on your Timesheets / OT Forms</p>
                </div>
                <div class="p-4">
                    @if($recentUpdates->isEmpty())
                        <p class="text-xs text-gray-500">No recent updates.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($recentUpdates as $update)
                                <li class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-900">
                                            @if($update['type'] === 'timesheet')
                                                Timesheet
                                            @else
                                                OT Form
                                            @endif
                                            <span class="text-gray-500">{{ ucfirst($update['action']) }}</span>
                                        </p>
                                        @if($update['model'])
                                            <p class="text-xs text-gray-500">
                                                {{ $update['model']->status_label }}
                                                @if($update['actor'])
                                                    by {{ $update['actor']->name }}
                                                @endif
                                                &middot; {{ $update['time']->diffForHumans() }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-help-button title="Dashboard Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Dashboard Overview</h3>
            <p class="mb-3">This is your main page showing a quick summary of your account.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Quick Links</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Timesheet</strong> — View and manage your monthly timesheets</li>
                <li><strong>OT Forms</strong> — Submit and track overtime requests</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">HR Activity</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Recent Actions</strong> — Your own create/edit/delete activity on Timesheets and OT Forms</li>
                <li><strong>Recent Updates</strong> — Status changes on your Timesheets and OT Forms</li>
            </ul>
            @if($canApproveTimesheets || $canApproveOtForms)
                <h4 class="font-semibold text-gray-900 mb-1">Pending Approvals</h4>
                <p class="mb-3">As an approver, you’ll see counts of Timesheets and OT Forms awaiting your approval. Click a card to review them.</p>
            @endif
            @if(Auth::user()->isAdmin())
                <h4 class="font-semibold text-gray-900 mb-1">Admin Cards</h4>
                <p>Cards below show active users, project codes, and last Desknet sync status. Click any card to manage that section.</p>
            @endif
            <h4 class="font-semibold text-gray-900 mb-1">OT Analytics</h4>
            <p>Charts above show approved OT hours by project, staff, and month. Use the month filter to change the project and staff charts.</p>
        </x-slot>
    </x-help-button>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Chart.register(ChartDataLabels);

                const monthlyData = @json($otMonthlyData->map(fn($r) => ['label' => $r->label, 'hours' => $r->hours]));
                const projectData = @json($otProjectData->map(fn($r) => ['label' => $r->label, 'hours' => $r->hours]));
                const staffData = @json($otStaffData->map(fn($r) => ['label' => $r->label, 'hours' => $r->hours]));

                // Common chart colors
                const colors = [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'
                ];

                function backgroundColors(count) {
                    const arr = [];
                    for (let i = 0; i < count; i++) {
                        arr.push(colors[i % colors.length]);
                    }
                    return arr;
                }

                // Monthly OT Hours Bar Chart
                new Chart(document.getElementById('monthlyOtChart'), {
                    type: 'bar',
                    data: {
                        labels: monthlyData.map(d => d.label),
                        datasets: [{
                            label: 'Total OT Hours',
                            data: monthlyData.map(d => d.hours),
                            backgroundColor: '#3B82F6',
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                color: '#374151',
                                font: { weight: 'bold', size: 11 },
                                formatter: function(value) {
                                    return value.toFixed(1);
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y.toFixed(2) + ' hours';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Hours' }
                            }
                        }
                    }
                });

                // OT Hours by Project Donut Chart
                new Chart(document.getElementById('projectOtChart'), {
                    type: 'doughnut',
                    data: {
                        labels: projectData.map(d => d.label),
                        datasets: [{
                            data: projectData.map(d => d.hours),
                            backgroundColor: backgroundColors(projectData.length),
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'right' },
                            datalabels: {
                                color: '#FFFFFF',
                                font: { weight: 'bold', size: 11 },
                                formatter: function(value) {
                                    return value.toFixed(2);
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                        return context.label + ': ' + context.parsed.toFixed(2) + ' hours (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });

                // OT Hours by Staff Bar Chart
                new Chart(document.getElementById('staffOtChart'), {
                    type: 'bar',
                    data: {
                        labels: staffData.map(d => d.label),
                        datasets: [{
                            label: 'Total OT Hours',
                            data: staffData.map(d => d.hours),
                            backgroundColor: backgroundColors(staffData.length),
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: {
                                color: '#FFFFFF',
                                font: { weight: 'bold', size: 11 },
                                anchor: 'center',
                                align: 'center',
                                formatter: function(value) {
                                    return value.toFixed(2);
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y.toFixed(2) + ' hours';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Hours' }
                            },
                            x: {
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 45,
                                    minRotation: 30,
                                    font: { size: 10 }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
