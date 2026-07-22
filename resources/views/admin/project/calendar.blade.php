<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Calendar</h2>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @php
            $baseQuery = ['view' => $view, 'day' => $day, 'month' => $month, 'year' => $year];
            $currentLabel = match($view) {
                'day' => now()->setDate($year, $month, $day)->format('l, j F Y'),
                'week' => $periodStart->format('j F Y') . ' - ' . $periodEnd->format('j F Y'),
                'month' => now()->setDate($year, $month, 1)->format('F Y'),
                'year' => $year,
                default => now()->setDate($year, $month, 1)->format('F Y'),
            };

            $prevDate = now()->setDate($year, $month, $day)->copy();
            $nextDate = now()->setDate($year, $month, $day)->copy();
            switch($view) {
                case 'day':
                    $prevDate->subDay();
                    $nextDate->addDay();
                    break;
                case 'week':
                    $prevDate->subWeek();
                    $nextDate->addWeek();
                    break;
                case 'month':
                    $prevDate->subMonth();
                    $nextDate->addMonth();
                    break;
                case 'year':
                    $prevDate->subYear();
                    $nextDate->addYear();
                    break;
            }
        @endphp

        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
            {{-- Toolbar --}}
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
                {{-- Left: Today + navigation --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.project.calendar', ['view' => $view]) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                        Today
                    </a>
                    <a href="{{ route('admin.project.calendar', array_merge($baseQuery, ['day' => $prevDate->day, 'month' => $prevDate->month, 'year' => $prevDate->year])) }}"
                       class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <h3 class="text-lg font-semibold text-gray-900 text-center">{{ $currentLabel }}</h3>
                    <a href="{{ route('admin.project.calendar', array_merge($baseQuery, ['day' => $nextDate->day, 'month' => $nextDate->month, 'year' => $nextDate->year])) }}"
                       class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Right: view + jump controls --}}
                <form method="GET" action="{{ route('admin.project.calendar') }}" class="flex items-center gap-2" onchange="this.submit()">
                    <input type="hidden" name="day" value="{{ $day }}">

                    <select name="view" class="border-gray-300 text-gray-700 text-sm rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $v => $label)
                            <option value="{{ $v }}" {{ $view === $v ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>

                    @if($view !== 'year')
                        <select name="month" class="border-gray-300 text-gray-700 text-sm rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ now()->setDate($year, $m, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    @endif

                    <select name="year" class="border-gray-300 text-gray-700 text-sm rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        @for($y = date('Y') + 2; $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>

                    <button type="submit" class="inline-flex items-center text-gray-500 hover:text-gray-700 px-2" title="Apply">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </button>
                </form>
            </div>

            {{-- Day View --}}
            @if($view === 'day')
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="mb-3">
                        <h4 class="text-md font-semibold text-gray-800">{{ now()->setDate($year, $month, $day)->format('l, j F Y') }}</h4>
                        <p class="text-sm text-gray-500">{{ $dayTasks->count() }} task(s)</p>
                    </div>
                    <div class="space-y-2">
                        @forelse($dayTasks as $task)
                            <div class="flex items-center justify-between p-3 rounded-md border
                                        {{ $task->status === 'completed' ? 'bg-green-50 border-green-200' : '' }}
                                        {{ $task->status === 'in_progress' ? 'bg-blue-50 border-blue-200' : '' }}
                                        {{ $task->status === 'on_hold' ? 'bg-yellow-50 border-yellow-200' : '' }}
                                        {{ $task->status === 'not_started' ? 'bg-gray-50 border-gray-200' : '' }}
                                        {{ $task->status === 'cancelled' ? 'bg-red-50 border-red-200' : '' }}">
                                <div>
                                    <a href="{{ route('admin.project.projects.show', $task->project) }}" class="text-sm font-medium text-indigo-700 hover:underline">
                                        {{ $task->task_name }}
                                    </a>
                                    <p class="text-xs text-gray-500">
                                        {{ $task->project->project_name }} &bull; {{ $task->start_date_plan->format('j M Y') }} - {{ $task->end_date_plan->format('j M Y') }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-white border text-gray-700 capitalize">{{ str_replace('_', ' ', $task->status) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No tasks for this day.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- Week / Month View --}}
            @if($view === 'week' || $view === 'month')
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                    {{-- Header row --}}
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr);" class="border-b border-gray-200 bg-gray-50">
                        @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $i => $dayName)
                            <div class="text-center py-2 text-xs font-semibold text-gray-600 {{ $i < 6 ? 'border-r border-gray-200' : '' }}">{{ $dayName }}</div>
                        @endforeach
                    </div>

                    {{-- Week rows --}}
                    @foreach($weeks as $weekIndex => $week)
                        <div class="week-section {{ $weekIndex > 0 ? 'border-t border-gray-200' : '' }}" style="position: relative; min-height: 120px;">
                            {{-- Vertical grid lines --}}
                            <div style="position: absolute; inset: 0; display: grid; grid-template-columns: repeat(7, 1fr); pointer-events: none; z-index: 0;">
                                @for($col = 0; $col < 7; $col++)
                                    <div class="{{ $col < 6 ? 'border-r border-gray-100' : '' }}"></div>
                                @endfor
                            </div>

                            {{-- Date numbers row --}}
                            <div style="display: grid; grid-template-columns: repeat(7, 1fr); position: relative; z-index: 1;">
                                @foreach($week['days'] as $day)
                                    <div class="px-2 pt-2 pb-1">
                                        <span class="text-sm font-medium {{ !$day['in_month'] ? 'text-gray-300' : ($day['date']->isToday() ? 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs' : 'text-gray-900') }}">
                                            {{ $day['date']->format('d') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Task bars --}}
                            <div class="pb-2" style="position: relative; z-index: 1;">
                                @if($week['max_level'] >= 0)
                                    @for($level = 0; $level <= $week['max_level']; $level++)
                                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); min-height: 28px; margin-bottom: 2px;">
                                            @foreach($week['bars'] as $bar)
                                                @if($bar['level'] === $level)
                                                    <div style="grid-column: {{ $bar['start_col'] + 1 }} / span {{ $bar['end_col'] - $bar['start_col'] + 1 }};" class="px-1">
                                                        <a href="{{ route('admin.project.projects.show', $bar['task']->project) }}"
                                                           class="task-bar-link block h-full rounded px-2 py-1 text-[11px] leading-tight truncate"
                                                           data-status="{{ $bar['task']->status }}"
                                                           title="{{ $bar['task']->task_name }} ({{ $bar['task']->project->project_name }})">
                                                            <span class="font-medium">{{ $bar['task']->task_name }}</span>
                                                            <br><span class="opacity-75 text-[10px]">{{ $bar['task']->start_date_plan->format('M j') }} - {{ $bar['task']->end_date_plan->format('M j, Y') }}</span>
                                                        </a>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endfor
                                @else
                                    <div style="min-height: 60px;"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Year View --}}
            @if($view === 'year')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($months as $monthData)
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800 border-b border-gray-200">{{ $monthData['name'] }}</div>
                            <div class="grid" style="grid-template-columns: repeat(7, minmax(0, 1fr)); background-color: #e5e7eb; gap: 1px;">
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">S</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">M</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">T</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">W</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">T</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">F</div>
                                <div class="bg-white text-center py-1 text-[10px] font-medium text-gray-500">S</div>

                                @foreach($monthData['weeks'] as $week)
                                    @foreach($week['days'] as $day)
                                        <div class="bg-white min-h-[40px] p-1 {{ $day['in_month'] ? '' : 'bg-gray-50 text-gray-300' }} {{ $day['date']->isToday() ? 'ring-2 ring-inset ring-indigo-200' : '' }}">
                                            <div class="text-[10px] font-medium {{ $day['in_month'] ? 'text-gray-700' : 'text-gray-300' }}">{{ $day['date']->day }}</div>
                                            @if($day['tasks']->count() > 0)
                                                <div class="mt-1 flex flex-wrap gap-0.5">
                                                    @foreach($day['tasks']->take(3) as $task)
                                                        <span class="w-2 h-2 rounded-full task-dot" data-status="{{ $task->status }}"></span>
                                                    @endforeach
                                                    @if($day['tasks']->count() > 3)
                                                        <span class="text-[8px] text-gray-500">+{{ $day['tasks']->count() - 3 }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Milestones placeholder --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Milestones</h3>
                <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">Coming soon</span>
            </div>
            <p class="text-sm text-gray-500">Milestone tracking will be added here in a future update.</p>
        </div>
    </div>

    <style>
        .task-bar-link[data-status="completed"] { background-color: #dcfce7; color: #166534; border-left: 3px solid #22c55e; }
        .task-bar-link[data-status="in_progress"] { background-color: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6; }
        .task-bar-link[data-status="on_hold"] { background-color: #fef9c3; color: #854d0e; border-left: 3px solid #eab308; }
        .task-bar-link[data-status="not_started"] { background-color: #f3f4f6; color: #374151; border-left: 3px solid #9ca3af; }
        .task-bar-link[data-status="cancelled"] { background-color: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
        .task-bar-link:hover { opacity: 0.85; }
        .task-badge[data-status="completed"] { background-color: #dcfce7; color: #166534; }
        .task-badge[data-status="in_progress"] { background-color: #dbeafe; color: #1e40af; }
        .task-badge[data-status="on_hold"] { background-color: #fef9c3; color: #854d0e; }
        .task-badge[data-status="not_started"] { background-color: #f3f4f6; color: #374151; }
        .task-badge[data-status="cancelled"] { background-color: #fee2e2; color: #991b1b; }
        .task-dot[data-status="completed"] { background-color: #22c55e; }
        .task-dot[data-status="in_progress"] { background-color: #3b82f6; }
        .task-dot[data-status="on_hold"] { background-color: #eab308; }
        .task-dot[data-status="not_started"] { background-color: #9ca3af; }
        .task-dot[data-status="cancelled"] { background-color: #ef4444; }
    </style>
</x-app-layout>
