@php
    // Get all phases with their tasks
    $phases = $project->phases()->with(['tasks' => function($q) {
        $q->with(['assignedTo'])->orderBy('task_order');
    }])->orderBy('phase_order')->get();

    // Get tasks without a phase (standalone tasks)
    $standaloneTasks = $project->tasks()->whereNull('phase_id')->with('assignedTo')->orderBy('task_order')->get();

    // Calculate timeline range using effective dates
    $allDates = collect();
    foreach($phases as $phase) {
        foreach($phase->tasks as $task) {
            $effective = $effectiveDates[$task->id] ?? null;

            if($effective && $effective['start_date']) $allDates->push($effective['start_date']);
            if($effective && $effective['end_date']) $allDates->push($effective['end_date']);

            if($task->start_date_plan) $allDates->push($task->start_date_plan);
            if($task->end_date_plan) $allDates->push($task->end_date_plan);
            if($task->start_date_actual) $allDates->push($task->start_date_actual);
            if($task->end_date_actual) $allDates->push($task->end_date_actual);
            if($task->start_date_revise) $allDates->push($task->start_date_revise);
            if($task->end_date_revise) $allDates->push($task->end_date_revise);
        }
    }
    foreach($standaloneTasks as $task) {
        $effective = $effectiveDates[$task->id] ?? null;

        if($effective && $effective['start_date']) $allDates->push($effective['start_date']);
        if($effective && $effective['end_date']) $allDates->push($effective['end_date']);

        if($task->start_date_plan) $allDates->push($task->start_date_plan);
        if($task->end_date_plan) $allDates->push($task->end_date_plan);
        if($task->start_date_actual) $allDates->push($task->start_date_actual);
        if($task->end_date_actual) $allDates->push($task->end_date_actual);
        if($task->start_date_revise) $allDates->push($task->start_date_revise);
        if($task->end_date_revise) $allDates->push($task->end_date_revise);
    }

    if($allDates->isEmpty()) {
        $timelineStart = now()->subDays(30);
        $timelineEnd = now()->addDays(90);
    } else {
        $timelineStart = $allDates->min()->subDays(7);
        $timelineEnd = $allDates->max()->addDays(7);
    }

    $totalDays = $timelineStart->diffInDays($timelineEnd) + 1;

    // Build timeline header structure (year, month, day)
    $yearBlocks = [];
    $monthBlocks = [];
    $dayLabels = [];

    $currentYear = null;
    $currentYearStart = null;
    $currentMonth = null;
    $currentMonthStart = null;

    for ($i = 0; $i < $totalDays; $i++) {
        $date = $timelineStart->copy()->addDays($i);
        $year = $date->year;
        $month = $date->format('M');
        $day = $date->day;

        if ($year !== $currentYear) {
            if ($currentYear !== null) {
                $yearBlocks[] = ['year' => $currentYear, 'start' => $currentYearStart, 'end' => $i - 1];
            }
            $currentYear = $year;
            $currentYearStart = $i;
        }

        if ($month !== $currentMonth) {
            if ($currentMonth !== null) {
                $monthBlocks[] = ['month' => $currentMonth, 'start' => $currentMonthStart, 'end' => $i - 1];
            }
            $currentMonth = $month;
            $currentMonthStart = $i;
        }

        $dayLabels[] = ['day' => $day, 'offset' => $i];
    }

    // Close final blocks
    if ($currentYear !== null) {
        $yearBlocks[] = ['year' => $currentYear, 'start' => $currentYearStart, 'end' => $totalDays - 1];
    }
    if ($currentMonth !== null) {
        $monthBlocks[] = ['month' => $currentMonth, 'start' => $currentMonthStart, 'end' => $totalDays - 1];
    }

    $dayWidth = 30;

    // Today's vertical line position (compare dates only to avoid time drift)
    $today = now('Asia/Kuala_Lumpur')->copy()->startOfDay();
    $todayOffset = $timelineStart->copy()->startOfDay()->diffInDays($today);
    $showTodayLine = $todayOffset >= 0 && $todayOffset <= $totalDays;
    $timelineLeftOffset = 688; // 256 + 96 + 80 + 128 + 128 (sticky column widths)
    $debugToday = $today->format('Y-m-d H:i:s e');
    $debugTimelineStart = $timelineStart->copy()->startOfDay()->format('Y-m-d');
    $debugTodayOffset = $todayOffset;
    $debugLabelAtToday = $dayLabels[$todayOffset]['day'] ?? 'N/A';
    $debugLineLeft = $timelineLeftOffset + ($todayOffset * $dayWidth);
@endphp

<div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
    {{-- Debug: remove after confirming --}}
    <div class="px-4 py-1 text-[10px] text-gray-400 bg-gray-50 border-b border-gray-100">
        System today: {{ $debugToday }} | Timeline start: {{ $debugTimelineStart }} | Today offset: {{ $debugTodayOffset }} | Label at offset: {{ $debugLabelAtToday }} | Line left: {{ $debugLineLeft }}px
    </div>

    <style>
        .phase-toggle-btn.collapsed svg {
            transform: rotate(-90deg);
        }
        .task-row.hidden {
            display: none;
        }
        .gantt-dot {
            position: absolute;
            top: 50%;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #4b5563;
            transform: translateY(-50%);
            cursor: crosshair;
            z-index: 20;
            pointer-events: auto;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.8);
        }
        .gantt-dot-left {
            left: -4px;
            transform: translate(-50%, -50%);
        }
        .gantt-dot-right {
            right: -4px;
            transform: translate(50%, -50%);
        }
        .gantt-dot:hover {
            background: #4b5563;
            border-color: #1f2937;
            transform: translate(-50%, -50%) scale(1.2);
        }
        .gantt-dot-right:hover {
            transform: translate(50%, -50%) scale(1.2);
        }
        .gantt-resize-handle {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 10px;
            z-index: 10;
            cursor: ew-resize;
            border-radius: 4px;
        }
        .gantt-resize-handle-left { left: -5px; }
        .gantt-resize-handle-right { right: -5px; }
        .gantt-resize-handle:hover { background-color: rgba(255,255,255,0.35); }
        #gantt-dependency-arrows path {
            pointer-events: stroke;
            cursor: pointer;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        #gantt-dependency-arrows path:hover {
            stroke: #111827;
            stroke-width: 2.5;
        }
        .gantt-drag-arrow {
            pointer-events: none;
        }
    </style>

    @if(!empty($dependencyError))
        <div class="px-6 py-3 bg-red-50 border-b border-red-100">
            <div class="flex items-center gap-2 text-red-700 text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Dependency error: {{ $dependencyError }}</span>
            </div>
        </div>
    @endif
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Project Schedule / Gantt Chart</h3>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-4 text-xs mr-4">
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-blue-400 rounded"></div>
                    <span class="text-gray-600">Plan</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-orange-400 rounded"></div>
                    <span class="text-gray-600">Revise</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-green-500 rounded"></div>
                    <span class="text-gray-600">Actual</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-green-300 rounded border border-green-500 border-dashed"></div>
                    <span class="text-gray-600">Effective</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mr-2">
                <div class="relative" id="gantt-zoom-toggle">
                    <button type="button" id="gantt-zoom-trigger" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition">
                        <span id="gantt-zoom-trigger-label" class="w-10 text-center">Day</span>
                        <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="gantt-zoom-menu" class="hidden absolute right-0 mt-1 w-20 bg-white border border-gray-200 rounded-md shadow-lg z-50 p-1">
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="day">Day</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="week">Week</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="month">Month</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="year">Year</button>
                    </div>
                </div>
            </div>
            <button type="button" id="gantt-fullscreen-btn" class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition mr-2" title="Fullscreen">
                <svg id="gantt-icon-expand" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                <svg id="gantt-icon-contract" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4M4 10V6m0 0h4M4 6l5 5m11-5l-5 5m5-5V6m0 0h-4"/>
                </svg>
            </button>
            <div class="relative mr-2" id="gantt-display-toggle">
                <button type="button" id="gantt-display-btn" class="inline-flex items-center gap-1.5 px-2 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-wider hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span>Show/Hide</span>
                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div id="gantt-display-menu" class="hidden absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-md shadow-lg z-50 p-2">
                    <label class="flex items-center gap-2 text-xs text-gray-700 py-1 cursor-pointer hover:bg-gray-50 px-1 rounded">
                        <input type="checkbox" class="gantt-visibility-toggle rounded text-indigo-600 focus:ring-indigo-500" data-target="plan" checked>
                        <span>Plan</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 py-1 cursor-pointer hover:bg-gray-50 px-1 rounded">
                        <input type="checkbox" class="gantt-visibility-toggle rounded text-indigo-600 focus:ring-indigo-500" data-target="revise" checked>
                        <span>Revise</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 py-1 cursor-pointer hover:bg-gray-50 px-1 rounded">
                        <input type="checkbox" class="gantt-visibility-toggle rounded text-indigo-600 focus:ring-indigo-500" data-target="actual" checked>
                        <span>Actual</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 py-1 cursor-pointer hover:bg-gray-50 px-1 rounded">
                        <input type="checkbox" class="gantt-visibility-toggle rounded text-indigo-600 focus:ring-indigo-500" data-target="dependencies" checked>
                        <span>Dependencies</span>
                    </label>
                </div>
            </div>
            <a id="gantt-export-excel-btn" href="{{ route('admin.project.projects.tasks.gantt-export-excel', $project) }}"
               class="inline-flex items-center px-2 py-1 bg-green-600 border border-transparent rounded-md font-semibold text-[10px] text-white uppercase tracking-wider hover:bg-green-700 transition">
                Download Excel
            </a>
            <a href="{{ route('admin.project.projects.phases.create', $project) . '?' . http_build_query(['redirect' => request()->fullUrl()]) }}"
               class="inline-flex items-center px-2 py-1 bg-indigo-600 border border-transparent rounded-md font-semibold text-[10px] text-white uppercase tracking-wider hover:bg-indigo-700 transition">
                Add Phase
            </a>
            <a href="{{ route('admin.project.projects.tasks.create', $project) . '?' . (request()->getQueryString() ?: 'tab=schedule') }}"
               class="inline-flex items-center px-2 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-wider hover:bg-gray-50 transition">
                Add Task
            </a>
        </div>
    </div>
    <div id="gantt-pending-actions" class="hidden px-6 py-2 bg-yellow-50 border-b border-yellow-100 flex items-center justify-between">
        <span id="gantt-pending-text" class="text-xs text-yellow-800 font-medium">No unsaved changes</span>
        <div class="flex items-center gap-2">
            <button id="gantt-pending-discard" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">Discard All</button>
            <button id="gantt-pending-save" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition">Save All</button>
        </div>
    </div>

    @if($phases->isEmpty() && $standaloneTasks->isEmpty())
        <div class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            <p class="text-sm text-gray-500 mt-2">No phases or tasks yet.</p>
            <p class="text-xs text-gray-400 mt-1">Add phases and tasks to see the Gantt chart.</p>
        </div>
    @else
        <div id="gantt-chart-container" class="relative" data-recent-changes-url="{{ route('admin.project.projects.tasks.gantt-changes', $project) }}">
            <div class="overflow-x-auto">
            <div id="gantt-wrapper" class="relative inline-block" style="min-width: max-content;">
                @if($showTodayLine)
                    <div id="gantt-today-line" class="absolute z-20 pointer-events-none border-l-2 border-red-500 border-dashed"
                         style="left: {{ $timelineLeftOffset + ($todayOffset * $dayWidth) }}px; top: 0; bottom: 0;">
                        <div class="absolute top-0 -translate-x-1/2 bg-red-500 text-white text-[9px] px-1.5 py-0.5 rounded shadow-sm whitespace-nowrap">
                            Today
                        </div>
                    </div>
                @endif
                <table id="gantt-table" class="border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="sticky left-0 bg-gray-50 z-40 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-64">
                            Task
                        </th>
                        <th class="sticky left-64 bg-gray-50 z-40 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-24">
                            Assigned To
                        </th>
                        <th class="bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-20">
                            Progress
                        </th>
                        <th class="bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-32">
                            Start
                        </th>
                        <th class="bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-32">
                            End
                        </th>
                        {{-- Timeline Header --}}
                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Timeline ({{ $timelineStart->format('M d') }} — {{ $timelineEnd->format('M d Y') }})
                        </th>
                    </tr>
                    {{-- Timeline Date Header Row --}}
                    <tr class="bg-gray-50">
                        <th class="sticky left-0 bg-gray-50 z-40 border-r border-gray-200"></th>
                        <th class="sticky left-64 bg-gray-50 z-40 border-r border-gray-200"></th>
                        <th class="bg-gray-50 border-r border-gray-200"></th>
                        <th class="bg-gray-50 border-r border-gray-200"></th>
                        <th class="bg-gray-50 border-r border-gray-200"></th>
                        <th class="px-0 py-0 border-b border-gray-200">
                            <div id="gantt-timeline-header" class="relative" data-timeline-start="{{ $timelineStart->format('Y-m-d') }}" data-timeline-end="{{ $timelineEnd->format('Y-m-d') }}" data-total-days="{{ $totalDays }}" data-timeline-left-offset="{{ $timelineLeftOffset }}" data-today-offset="{{ $todayOffset }}" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
                                {{-- Year row --}}
                                <div class="absolute" style="left: 0; right: 0; top: 0; height: 22px; border-bottom: 1px solid #d1d5db;">
                                    @foreach($yearBlocks as $block)
                                        <div class="absolute h-full flex items-center justify-center text-[10px] font-semibold text-gray-700 bg-gray-100" 
                                             style="left: {{ $block['start'] * $dayWidth }}px; width: {{ ($block['end'] - $block['start'] + 1) * $dayWidth }}px; border-right: 1px solid #d1d5db;">
                                            {{ $block['year'] }}
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Month row --}}
                                <div class="absolute" style="left: 0; right: 0; top: 22px; height: 22px; border-bottom: 1px solid #d1d5db;">
                                    @foreach($monthBlocks as $block)
                                        <div class="absolute h-full flex items-center justify-center text-[10px] font-semibold text-gray-700 bg-gray-50" 
                                             style="left: {{ $block['start'] * $dayWidth }}px; width: {{ ($block['end'] - $block['start'] + 1) * $dayWidth }}px; border-right: 1px solid #d1d5db;">
                                            {{ $block['month'] }}
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Day row --}}
                                <div class="absolute" style="left: 0; right: 0; top: 44px; height: 26px;">
                                    @foreach($dayLabels as $label)
                                        <div class="absolute h-full flex items-center justify-center text-[9px] text-gray-600"
                                             @if($label['offset'] == $todayOffset) id="gantt-today-marker" @endif
                                             style="left: {{ $label['offset'] * $dayWidth }}px; width: {{ $dayWidth }}px; border-right: 1px solid #e5e7eb;">
                                            {{ $label['day'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($phases as $phase)
                        {{-- Phase Row --}}
                        <tr class="bg-gray-100 phase-row" data-phase-id="{{ $phase->id }}">
                            <td class="sticky left-0 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            class="phase-toggle-btn text-gray-500 hover:text-gray-700 focus:outline-none"
                                            data-phase-id="{{ $phase->id }}"
                                            title="Toggle tasks">
                                        <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div class="gantt-menu">
                                        <button type="button" class="gantt-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100" title="Phase actions"
                                                data-edit-url="{{ route('admin.project.projects.phases.edit', [$project, $phase]) . '?' . http_build_query(['redirect' => request()->fullUrl()]) }}"
                                                data-delete-action="{{ route('admin.project.projects.phases.destroy', [$project, $phase]) }}"
                                                data-delete-confirm="Delete this phase and all its tasks?">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <span class="font-semibold text-gray-900">{{ $phase->phase_name }}</span>
                                    <span class="text-xs text-gray-500">#{{ $phase->phase_order }}</span>
                                </div>
                            </td>
                            <td class="sticky left-64 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
                                <div class="space-y-0.5">
                                    <div class="text-blue-600">P: {{ $phase->progress_plan }}%</div>
                                    @if($phase->start_date_revise && $phase->end_date_revise)
                                    @php
                                        $today = \Carbon\Carbon::today();
                                        $reviseStart = $phase->start_date_revise->copy()->startOfDay();
                                        $reviseEnd = $phase->end_date_revise->copy()->startOfDay();
                                        if ($today->lte($reviseStart)) {
                                            $reviseProgress = 0;
                                        } elseif ($today->gte($reviseEnd)) {
                                            $reviseProgress = 100;
                                        } else {
                                            $totalDays = $reviseStart->diffInDays($reviseEnd);
                                            $reviseProgress = $totalDays > 0 ? (int) round(($reviseStart->diffInDays($today) / $totalDays) * 100) : 100;
                                        }
                                    @endphp
                                    <div class="text-orange-600">R: {{ $reviseProgress }}%</div>
                                    @endif
                                    <div class="text-green-600">A: {{ $phase->progress_actual }}%</div>
                                </div>
                            </td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
                                <div class="space-y-0.5">
                                    @if($phase->start_date_plan) <div class="text-gray-500">P: {{ $phase->start_date_plan->format('d/m/Y') }}</div> @endif
                                    @if($phase->start_date_revise) <div class="text-orange-500">R: {{ $phase->start_date_revise->format('d/m/Y') }}</div> @endif
                                    @if($phase->start_date_actual) <div class="text-green-600">A: {{ $phase->start_date_actual->format('d/m/Y') }}</div> @endif
                                </div>
                            </td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
                                <div class="space-y-0.5">
                                    @if($phase->end_date_plan) <div class="text-gray-500">P: {{ $phase->end_date_plan->format('d/m/Y') }}</div> @endif
                                    @if($phase->end_date_revise) <div class="text-orange-500">R: {{ $phase->end_date_revise->format('d/m/Y') }}</div> @endif
                                    @if($phase->end_date_actual) <div class="text-green-600">A: {{ $phase->end_date_actual->format('d/m/Y') }}</div> @endif
                                </div>
                            </td>
                            <td class="px-0 py-2 bg-gray-100">
                                @php
                                    // Plan bar
                                    $phasePlanStartOffset = $phase->start_date_plan ? $timelineStart->diffInDays($phase->start_date_plan) : null;
                                    $phasePlanDuration = $phase->start_date_plan && $phase->end_date_plan ? $phase->start_date_plan->diffInDays($phase->end_date_plan) + 1 : null;

                                    // Revise bar
                                    $phaseReviseStartOffset = $phase->start_date_revise ? $timelineStart->diffInDays($phase->start_date_revise) : null;
                                    $phaseReviseDuration = $phase->start_date_revise && $phase->end_date_revise ? $phase->start_date_revise->diffInDays($phase->end_date_revise) + 1 : null;

                                    // Actual bar
                                    $phaseActualStart = $phase->start_date_actual;
                                    $phaseActualEnd = $phase->end_date_actual;
                                    $phaseActualStartOffset = $phaseActualStart ? $timelineStart->diffInDays($phaseActualStart) : null;
                                    if ($phaseActualStart) {
                                        $phaseActualEndForBar = $phaseActualEnd ?: now('Asia/Kuala_Lumpur')->copy()->startOfDay();
                                        if ($phaseActualEndForBar->lt($phaseActualStart)) {
                                            $phaseActualEndForBar = $phaseActualStart->copy();
                                        }
                                        $phaseActualDuration = $phaseActualStart->diffInDays($phaseActualEndForBar) + 1;
                                    } else {
                                        $phaseActualDuration = null;
                                    }
                                @endphp
                                <div class="gantt-timeline-area relative" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
                                    @for($i = 0; $i <= $totalDays; $i++)
                                        <div class="gantt-grid-line absolute" data-day-offset="{{ $i }}" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
                                    @endfor
                                    {{-- Plan bar --}}
                                    @if($phasePlanStartOffset !== null && $phasePlanDuration !== null)
                                        <div class="gantt-bar absolute" data-start-offset="{{ $phasePlanStartOffset }}" data-duration="{{ $phasePlanDuration }}" data-bar-type="plan"
                                             style="left: {{ $phasePlanStartOffset * $dayWidth }}px; top: 8px; width: {{ max($phasePlanDuration * $dayWidth, 4) }}px; height: 16px; background-color: #a855f7; border: 1px solid #9333ea; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                             title="Plan: {{ $phase->start_date_plan->format('d M Y') }} — {{ $phase->end_date_plan->format('d M Y') }}">
                                            <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $phase->progress_plan }}%</span>
                                        </div>
                                    @endif
                                    {{-- Revise bar --}}
                                    @if($phaseReviseStartOffset !== null && $phaseReviseDuration !== null)
                                        @php
                                            $today = \Carbon\Carbon::today();
                                            $reviseStart = $phase->start_date_revise->copy()->startOfDay();
                                            $reviseEnd = $phase->end_date_revise->copy()->startOfDay();
                                            if ($today->lte($reviseStart)) {
                                                $reviseProgress = 0;
                                            } elseif ($today->gte($reviseEnd)) {
                                                $reviseProgress = 100;
                                            } else {
                                                $totalDays = $reviseStart->diffInDays($reviseEnd);
                                                $reviseProgress = $totalDays > 0 ? (int) round(($reviseStart->diffInDays($today) / $totalDays) * 100) : 100;
                                            }
                                        @endphp
                                        <div class="gantt-bar absolute" data-start-offset="{{ $phaseReviseStartOffset }}" data-duration="{{ $phaseReviseDuration }}" data-bar-type="revise"
                                             style="left: {{ $phaseReviseStartOffset * $dayWidth }}px; top: 28px; width: {{ max($phaseReviseDuration * $dayWidth, 4) }}px; height: 16px; background-color: #fb923c; border: 1px solid #f97316; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                             title="Revise: {{ $phase->start_date_revise->format('d M Y') }} — {{ $phase->end_date_revise->format('d M Y') }}">
                                            <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $reviseProgress }}%</span>
                                        </div>
                                    @endif
                                    {{-- Actual bar --}}
                                    @if($phaseActualStartOffset !== null && $phaseActualDuration !== null)
                                        @php
                                            $phaseIsOngoing = !$phaseActualEnd;
                                            $phaseActualTitleEnd = $phaseIsOngoing
                                                ? now('Asia/Kuala_Lumpur')->copy()->startOfDay()->format('d M Y') . ' (ongoing)'
                                                : $phase->end_date_actual->format('d M Y');
                                            $phaseActualTitle = "Actual: " . $phase->start_date_actual->format('d M Y') . " — " . $phaseActualTitleEnd;
                                            $phaseActualStyle = $phaseIsOngoing
                                                ? "background-color: #86efac; border: 1px solid #22c55e; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                                : "background-color: #22c55e; border: 1px solid #16a34a; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);";
                                        @endphp
                                        <div class="gantt-bar absolute" data-start-offset="{{ $phaseActualStartOffset }}" data-duration="{{ $phaseActualDuration }}" data-bar-type="actual"
                                             style="left: {{ $phaseActualStartOffset * $dayWidth }}px; top: 48px; width: {{ max($phaseActualDuration * $dayWidth, 4) }}px; height: 16px; {{ $phaseActualStyle }}"
                                             title="{{ $phaseActualTitle }}">
                                            <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $phase->progress_actual }}%</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Phase Tasks --}}
                        @foreach($phase->tasks as $task)
                            @include('admin.project.partials._gantt_task_row', ['task' => $task, 'timelineStart' => $timelineStart, 'totalDays' => $totalDays, 'dayWidth' => $dayWidth, 'effectiveDates' => $effectiveDates, 'todayOffset' => $todayOffset])
                        @endforeach
                    @endforeach

                    {{-- Standalone Tasks --}}
                    @if($standaloneTasks->isNotEmpty())
                        <tr class="bg-gray-100">
                            <td class="sticky left-0 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200">
                                <span class="font-semibold text-gray-900">Standalone Tasks</span>
                            </td>
                            <td class="sticky left-64 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="px-0 py-2 bg-gray-100">
                                <div class="gantt-timeline-area relative" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
                                    @for($i = 0; $i <= $totalDays; $i++)
                                        <div class="gantt-grid-line absolute" data-day-offset="{{ $i }}" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
                                    @endfor
                                </div>
                            </td>
                        </tr>
                        @foreach($standaloneTasks as $task)
                            @include('admin.project.partials._gantt_task_row', ['task' => $task, 'timelineStart' => $timelineStart, 'totalDays' => $totalDays, 'dayWidth' => $dayWidth, 'effectiveDates' => $effectiveDates, 'todayOffset' => $todayOffset])
                        @endforeach
                    @endif
                </tbody>
            </table>
            <svg id="gantt-dependency-arrows" class="absolute top-0 left-0 pointer-events-none z-20" style="overflow: visible;" width="1" height="1">
                <defs>
                    <marker id="gantt-arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                        <path d="M0,0 L0,6 L9,3 z" fill="#6b7280"/>
                    </marker>
                </defs>
            </svg>
            </div>
        </div>
        <button id="gantt-exit-fullscreen" class="hidden fixed top-4 right-4 z-[60] inline-flex items-center justify-center w-10 h-10 rounded-full bg-white border border-gray-300 shadow-lg text-gray-700 hover:bg-gray-50 transition" title="Exit Fullscreen">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4M4 10V6m0 0h4M4 6l5 5m11-5l-5 5m5-5V6m0 0h-4"/>
            </svg>
        </button>
    </div>
    @endif
</div>

{{-- Task Quick Update Modal --}}
<div id="task-quick-update-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-lg shadow-xl w-96 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Update Task</h4>
            <button type="button" onclick="closeTaskUpdateModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="task-quick-update-form" method="POST" class="p-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Task</label>
                <p id="modal-task-name" class="text-sm text-gray-900 font-medium"></p>
            </div>
            <div>
                <label for="modal_progress_actual" class="block text-xs font-medium text-gray-700 mb-1">Progress (%)</label>
                <div class="flex items-center gap-3">
                    <input type="range" id="modal_progress_actual" name="progress_actual" min="0" max="100"
                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                           oninput="document.getElementById('modal-progress-label').textContent = this.value + '%'">
                    <span id="modal-progress-label" class="text-sm font-semibold text-gray-700 w-10 text-right"></span>
                </div>
            </div>
            <div>
                <label for="modal_status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select id="modal_status" name="status" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <option value="not_started">Not Started</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="on_hold">On Hold</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_start_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual Start Date</label>
                    <input type="date" id="modal_start_date_actual" name="start_date_actual" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_end_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual End Date</label>
                    <input type="date" id="modal_end_date_actual" name="end_date_actual" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_start_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise Start Date</label>
                    <input type="date" id="modal_start_date_revise" name="start_date_revise" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_end_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise End Date</label>
                    <input type="date" id="modal_end_date_revise" name="end_date_revise" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label for="modal_notes" class="block text-xs font-medium text-gray-700 mb-1">Notes (optional)</label>
                <textarea id="modal_notes" name="notes" rows="2" class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500" placeholder="Add update notes..."></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeTaskUpdateModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                    Save Task
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openTaskUpdateModal(btn) {
        document.getElementById('modal-task-name').textContent = btn.dataset.taskName;
        document.getElementById('modal_progress_actual').value = btn.dataset.taskProgress;
        document.getElementById('modal-progress-label').textContent = btn.dataset.taskProgress + '%';
        document.getElementById('modal_status').value = btn.dataset.taskStatus;
        document.getElementById('modal_start_date_actual').value = btn.dataset.taskStartDateActual || '';
        document.getElementById('modal_end_date_actual').value = btn.dataset.taskEndDateActual || '';
        document.getElementById('modal_start_date_revise').value = btn.dataset.taskStartDateRevise || '';
        document.getElementById('modal_end_date_revise').value = btn.dataset.taskEndDateRevise || '';
        document.getElementById('modal_notes').value = '';
        document.getElementById('task-quick-update-form').action = btn.dataset.taskUrl;
        document.getElementById('task-quick-update-modal').classList.remove('hidden');
    }

    function closeTaskUpdateModal() {
        document.getElementById('task-quick-update-modal').classList.add('hidden');
    }

    document.querySelectorAll('.task-update-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openTaskUpdateModal(btn);
        });
    });

    document.getElementById('task-quick-update-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskUpdateModal();
        }
    });

    document.querySelectorAll('.phase-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var phaseId = btn.dataset.phaseId;
            var isCollapsed = btn.classList.toggle('collapsed');
            document.querySelectorAll('.task-row[data-phase-id="' + phaseId + '"]').forEach(function(row) {
                row.classList.toggle('hidden', isCollapsed);
            });
        });
    });

    document.querySelectorAll('.phase-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.phase-toggle-btn') || e.target.closest('form') || e.target.closest('.gantt-menu')) return;
            var btn = row.querySelector('.phase-toggle-btn');
            if (btn) btn.click();
        });
    });

    function positionTodayLine() {
        var wrapper = document.getElementById('gantt-wrapper');
        var marker = document.getElementById('gantt-today-marker');
        var line = document.getElementById('gantt-today-line');
        if (!wrapper || !marker || !line) return;
        var m = marker.getBoundingClientRect();
        var w = wrapper.getBoundingClientRect();
        line.style.left = (m.left - w.left + m.width / 2) + 'px';
    }

    window.addEventListener('load', positionTodayLine);
    window.addEventListener('resize', positionTodayLine);
    positionTodayLine();

    // Shared fixed dropdown for gantt 3-dot menus
    var ganttDropdown = document.createElement('div');
    ganttDropdown.id = 'gantt-context-menu';
    ganttDropdown.className = 'hidden';
    ganttDropdown.style.cssText = 'position:absolute;z-index:9999;width:7rem;background:#fff;border-radius:0.375rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);border:1px solid #e5e7eb;padding:0.25rem 0;';
    ganttDropdown.innerHTML = '<a id="gcm-edit" href="#" style="display:block;padding:0.5rem 1rem;font-size:0.75rem;color:#374151;text-decoration:none;">Edit</a>' +
        '<a id="gcm-delete" href="#" style="display:block;padding:0.5rem 1rem;font-size:0.75rem;color:#dc2626;text-decoration:none;">Delete</a>';
    document.body.appendChild(ganttDropdown);

    var gcmEdit = document.getElementById('gcm-edit');
    var gcmDelete = document.getElementById('gcm-delete');
    var gcmDeleteAction = '';
    var gcmDeleteConfirm = '';
    var gcmCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    gcmEdit.addEventListener('mouseover', function() { this.style.backgroundColor = '#f3f4f6'; });
    gcmEdit.addEventListener('mouseout', function() { this.style.backgroundColor = ''; });
    gcmDelete.addEventListener('mouseover', function() { this.style.backgroundColor = '#f3f4f6'; });
    gcmDelete.addEventListener('mouseout', function() { this.style.backgroundColor = ''; });

    gcmDelete.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm(gcmDeleteConfirm)) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = gcmDeleteAction;
            form.innerHTML = '<input type="hidden" name="_token" value="' + gcmCsrfToken + '">' +
                             '<input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        }
        ganttDropdown.classList.add('hidden');
    });

    document.querySelectorAll('.gantt-menu-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var wasVisible = !ganttDropdown.classList.contains('hidden');
            ganttDropdown.classList.add('hidden');
            if (wasVisible) return;

            var rect = this.getBoundingClientRect();
            gcmEdit.href = this.dataset.editUrl;
            gcmDeleteAction = this.dataset.deleteAction;
            gcmDeleteConfirm = this.dataset.deleteConfirm;

            ganttDropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
            ganttDropdown.style.left = (rect.left + window.scrollX) + 'px';
            ganttDropdown.classList.remove('hidden');
        });
    });

    document.addEventListener('click', function() {
        ganttDropdown.classList.add('hidden');
    });

    ganttDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // --- Gantt Zoom Logic ---
    var GANTT_ZOOM = {
        day:   { pixelsPerDay: 30,  rows: ['year', 'month', 'day'] },
        week:  { pixelsPerDay: 20,  rows: ['year', 'month', 'week'] },
        month: { pixelsPerDay: 8,   rows: ['year', 'month'] },
        year:  { pixelsPerDay: 3,   rows: ['year'] }
    };

    function ganttDaysBetween(startStr, endStr) {
        var start = new Date(startStr + 'T00:00:00');
        var end = new Date(endStr + 'T00:00:00');
        return Math.round((end - start) / (1000 * 60 * 60 * 24));
    }

    function ganttGetHeaderRows(startStr, endStr, zoomLevel) {
        var start = new Date(startStr + 'T00:00:00');
        var end = new Date(endStr + 'T00:00:00');
        var totalDays = ganttDaysBetween(startStr, endStr);
        var rows = [];

        function addYearBlocks() {
            var blocks = [];
            var currentYear = start.getFullYear();
            var endYear = end.getFullYear();
            while (currentYear <= endYear) {
                var yearStart = new Date(currentYear, 0, 1);
                var yearEnd = new Date(currentYear, 11, 31);
                var blockStart = new Date(Math.max(yearStart.getTime(), start.getTime()));
                var blockEnd = new Date(Math.min(yearEnd.getTime(), end.getTime()));
                blocks.push({
                    startOffset: ganttDaysBetween(startStr, blockStart.toISOString().split('T')[0]),
                    endOffset: ganttDaysBetween(startStr, blockEnd.toISOString().split('T')[0]),
                    label: currentYear
                });
                currentYear++;
            }
            rows.push({ type: 'year', height: 22, blocks: blocks });
        }

        function addMonthBlocks() {
            var blocks = [];
            var current = new Date(start);
            while (current <= end) {
                var year = current.getFullYear();
                var month = current.getMonth();
                var monthStart = new Date(year, month, 1);
                var monthEnd = new Date(year, month + 1, 0);
                var blockStart = new Date(Math.max(monthStart.getTime(), start.getTime()));
                var blockEnd = new Date(Math.min(monthEnd.getTime(), end.getTime()));
                blocks.push({
                    startOffset: ganttDaysBetween(startStr, blockStart.toISOString().split('T')[0]),
                    endOffset: ganttDaysBetween(startStr, blockEnd.toISOString().split('T')[0]),
                    label: current.toLocaleString('en-US', { month: 'short' })
                });
                current.setMonth(current.getMonth() + 1);
            }
            rows.push({ type: 'month', height: 22, blocks: blocks });
        }

        function addWeekBlocks() {
            var blocks = [];
            var weekIndex = 1;
            for (var i = 0; i <= totalDays; i += 7) {
                var chunkEnd = Math.min(i + 6, totalDays);
                blocks.push({
                    startOffset: i,
                    endOffset: chunkEnd,
                    label: 'W' + weekIndex
                });
                weekIndex++;
            }
            rows.push({ type: 'week', height: 26, blocks: blocks });
        }

        function addDayBlocks() {
            var blocks = [];
            for (var i = 0; i <= totalDays; i++) {
                var d = new Date(start);
                d.setDate(d.getDate() + i);
                blocks.push({
                    startOffset: i,
                    endOffset: i,
                    label: d.getDate()
                });
            }
            rows.push({ type: 'day', height: 26, blocks: blocks });
        }

        GANTT_ZOOM[zoomLevel].rows.forEach(function(rowType) {
            if (rowType === 'year') addYearBlocks();
            if (rowType === 'month') addMonthBlocks();
            if (rowType === 'week') addWeekBlocks();
            if (rowType === 'day') addDayBlocks();
        });

        return rows;
    }

    function ganttRenderHeader(zoomLevel) {
        var header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        var startStr = header.dataset.timelineStart;
        var endStr = header.dataset.timelineEnd;
        var totalDays = parseInt(header.dataset.totalDays, 10);
        var todayOffset = parseInt(header.dataset.todayOffset, 10);
        var config = GANTT_ZOOM[zoomLevel];
        var pixelsPerDay = config.pixelsPerDay;
        var totalWidth = totalDays * pixelsPerDay;

        header.style.width = totalWidth + 'px';
        header.style.minWidth = totalWidth + 'px';

        var rows = ganttGetHeaderRows(startStr, endStr, zoomLevel);
        var html = '';
        var top = 0;

        rows.forEach(function(row, index) {
            var isLast = index === rows.length - 1;
            var borderBottom = isLast ? '' : 'border-bottom: 1px solid #d1d5db;';
            html += '<div class="absolute" style="left: 0; right: 0; top: ' + top + 'px; height: ' + row.height + 'px; ' + borderBottom + '">';
            row.blocks.forEach(function(block) {
                var blockWidth = (block.endOffset - block.startOffset + 1) * pixelsPerDay;
                var bgClass = row.type === 'year' ? 'bg-gray-100' : (row.type === 'month' ? 'bg-gray-50' : '');
                var bgStyle = bgClass ? '' : 'background-color: transparent;';
                var fontWeight = row.type === 'year' || row.type === 'month' ? 'font-semibold' : '';
                var fontSize = row.type === 'day' ? 'text-[9px]' : 'text-[10px]';
                var borderRight = row.type === 'day' ? 'border-right: 1px solid #e5e7eb;' : 'border-right: 1px solid #d1d5db;';
                var extraAttrs = '';
                var extraClasses = '';
                if (row.type === 'day' && block.startOffset === todayOffset) {
                    extraAttrs = ' id="gantt-today-marker"';
                    extraClasses = ' text-red-600 font-bold';
                }
                html += '<div class="absolute h-full flex items-center justify-center ' + fontSize + ' ' + fontWeight + ' text-gray-700 ' + bgClass + extraClasses + '"' +
                        extraAttrs +
                        ' style="left: ' + (block.startOffset * pixelsPerDay) + 'px; width: ' + blockWidth + 'px; ' + borderRight + ' ' + bgStyle + '">' +
                        block.label + '</div>';
            });
            html += '</div>';
            top += row.height;
        });

        header.style.height = top + 'px';
        header.innerHTML = html;
    }

    function ganttUpdateBars(zoomLevel) {
        var config = GANTT_ZOOM[zoomLevel];
        var pixelsPerDay = config.pixelsPerDay;
        document.querySelectorAll('.gantt-bar').forEach(function(bar) {
            var startOffset = parseFloat(bar.dataset.startOffset);
            var duration = parseFloat(bar.dataset.duration);
            if (isNaN(startOffset) || isNaN(duration)) return;
            bar.style.left = (startOffset * pixelsPerDay) + 'px';
            bar.style.width = Math.max(duration * pixelsPerDay, 4) + 'px';
        });
    }

    function ganttUpdateGridLines(zoomLevel) {
        var config = GANTT_ZOOM[zoomLevel];
        var pixelsPerDay = config.pixelsPerDay;
        var isDayView = zoomLevel === 'day';
        document.querySelectorAll('.gantt-grid-line').forEach(function(line) {
            if (!isDayView) {
                line.style.display = 'none';
                return;
            }
            line.style.display = '';
            var offset = parseFloat(line.dataset.dayOffset);
            if (isNaN(offset)) return;
            line.style.left = (offset * pixelsPerDay) + 'px';
        });
    }

    function ganttUpdateTimelineAreas(zoomLevel) {
        var config = GANTT_ZOOM[zoomLevel];
        var pixelsPerDay = config.pixelsPerDay;
        var header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        var totalDays = parseInt(header.dataset.totalDays, 10);
        var totalWidth = totalDays * pixelsPerDay;
        document.querySelectorAll('.gantt-timeline-area').forEach(function(area) {
            area.style.width = totalWidth + 'px';
            area.style.minWidth = totalWidth + 'px';
        });
    }

    function ganttUpdateTodayLine(zoomLevel) {
        var config = GANTT_ZOOM[zoomLevel];
        var pixelsPerDay = config.pixelsPerDay;
        var line = document.getElementById('gantt-today-line');
        if (!line) return;
        var header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        var todayOffset = parseInt(header.dataset.todayOffset, 10);
        var totalDays = parseInt(header.dataset.totalDays, 10);
        var timelineLeftOffset = parseInt(header.dataset.timelineLeftOffset, 10);
        if (todayOffset < 0 || todayOffset > totalDays) {
            line.style.display = 'none';
            return;
        }
        line.style.display = '';
        line.style.left = (timelineLeftOffset + (todayOffset * pixelsPerDay)) + 'px';
    }

    function ganttApplyZoom(zoomLevel) {
        window.ganttCurrentZoom = zoomLevel;
        ganttRenderHeader(zoomLevel);
        ganttUpdateBars(zoomLevel);
        ganttUpdateGridLines(zoomLevel);
        ganttUpdateTimelineAreas(zoomLevel);
        ganttUpdateTodayLine(zoomLevel);
        positionTodayLine();

        var zoomTriggerLabel = document.getElementById('gantt-zoom-trigger-label');
        if (zoomTriggerLabel) {
            zoomTriggerLabel.textContent = zoomLevel.charAt(0).toUpperCase() + zoomLevel.slice(1);
        }
        document.querySelectorAll('.gantt-zoom-btn').forEach(function(btn) {
            if (btn.dataset.zoom === zoomLevel) {
                btn.classList.add('bg-indigo-50', 'border-indigo-300', 'text-indigo-700');
                btn.classList.remove('bg-white', 'border-gray-300', 'text-gray-700');
            } else {
                btn.classList.remove('bg-indigo-50', 'border-indigo-300', 'text-indigo-700');
                btn.classList.add('bg-white', 'border-gray-300', 'text-gray-700');
            }
        });
    }

    function ganttInitZoom() {
        document.querySelectorAll('.gantt-zoom-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                ganttApplyZoom(this.dataset.zoom);
            });
        });
        ganttApplyZoom('day');
    }

    ganttInitZoom();

    var ganttZoomTrigger = document.getElementById('gantt-zoom-trigger');
    var ganttZoomMenu = document.getElementById('gantt-zoom-menu');
    if (ganttZoomTrigger && ganttZoomMenu) {
        ganttZoomTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            ganttZoomMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function(e) {
            if (!ganttZoomMenu.contains(e.target) && !ganttZoomTrigger.contains(e.target)) {
                ganttZoomMenu.classList.add('hidden');
            }
        });
    }

    // --- Fullscreen Toggle ---
    var ganttFullscreenBtn = document.getElementById('gantt-fullscreen-btn');
    var ganttExitFullscreenBtn = document.getElementById('gantt-exit-fullscreen');
    var ganttChartContainer = document.getElementById('gantt-chart-container');
    var ganttIconExpand = document.getElementById('gantt-icon-expand');
    var ganttIconContract = document.getElementById('gantt-icon-contract');
    var ganttIsFullscreen = false;
    var ganttOriginalScrollY = 0;

    function ganttEnterFullscreen() {
        if (!ganttChartContainer) return;
        ganttIsFullscreen = true;
        ganttOriginalScrollY = window.scrollY;
        ganttChartContainer.style.position = 'fixed';
        ganttChartContainer.style.top = '0';
        ganttChartContainer.style.left = '0';
        ganttChartContainer.style.width = '100vw';
        ganttChartContainer.style.height = '100vh';
        ganttChartContainer.style.zIndex = '50';
        ganttChartContainer.style.backgroundColor = '#fff';
        ganttChartContainer.style.overflow = 'auto';
        ganttChartContainer.style.padding = '1rem';
        if (ganttExitFullscreenBtn) ganttExitFullscreenBtn.classList.remove('hidden');
        if (ganttIconExpand) ganttIconExpand.classList.add('hidden');
        if (ganttIconContract) ganttIconContract.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function ganttExitFullscreen() {
        if (!ganttChartContainer) return;
        ganttIsFullscreen = false;
        ganttChartContainer.style.position = '';
        ganttChartContainer.style.top = '';
        ganttChartContainer.style.left = '';
        ganttChartContainer.style.width = '';
        ganttChartContainer.style.height = '';
        ganttChartContainer.style.zIndex = '';
        ganttChartContainer.style.backgroundColor = '';
        ganttChartContainer.style.overflow = '';
        ganttChartContainer.style.padding = '';
        if (ganttExitFullscreenBtn) ganttExitFullscreenBtn.classList.add('hidden');
        if (ganttIconExpand) ganttIconExpand.classList.remove('hidden');
        if (ganttIconContract) ganttIconContract.classList.add('hidden');
        document.body.style.overflow = '';
        window.scrollTo(0, ganttOriginalScrollY);
    }

    if (ganttFullscreenBtn) {
        ganttFullscreenBtn.addEventListener('click', function() {
            if (ganttIsFullscreen) {
                ganttExitFullscreen();
            } else {
                ganttEnterFullscreen();
            }
        });
    }

    if (ganttExitFullscreenBtn) {
        ganttExitFullscreenBtn.addEventListener('click', ganttExitFullscreen);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && ganttIsFullscreen) {
            ganttExitFullscreen();
        }
    });

    // --- Dependency Arrows ---
    function getGanttBarForTask(wrapper, taskId, barType) {
        var bar = wrapper.querySelector('.gantt-bar[data-task-id="' + taskId + '"][data-bar-type="' + barType + '"]');
        if (bar) {
            var rect = bar.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) return bar;
        }
        return null;
    }

    function parseGanttDependencyType(type) {
        if (type && type.indexOf('start_to_start') !== -1) {
            return { fromSide: 'start', toSide: 'start' };
        }
        return { fromSide: 'end', toSide: 'start' };
    }

    function drawGanttDependencyArrowForLane(wrapper, svg, wrapperRect, taskId, predecessorId, dependencyType, lane) {
        var typeParts = parseGanttDependencyType(dependencyType);
        var successorBar = getGanttBarForTask(wrapper, taskId, lane);
        var predecessorBar = getGanttBarForTask(wrapper, predecessorId, lane);
        if (!successorBar || !predecessorBar) return;

        var fromRect = predecessorBar.getBoundingClientRect();
        var toRect = successorBar.getBoundingClientRect();
        if (fromRect.width === 0 || fromRect.height === 0 || toRect.width === 0 || toRect.height === 0) return;

        var x2 = typeParts.toSide === 'start'
            ? (toRect.left - wrapperRect.left)
            : (toRect.right - wrapperRect.left);
        var y2 = (toRect.top + toRect.bottom) / 2 - wrapperRect.top;
        var x1 = typeParts.fromSide === 'start'
            ? (fromRect.left - wrapperRect.left)
            : (fromRect.right - wrapperRect.left);
        var y1 = (fromRect.top + fromRect.bottom) / 2 - wrapperRect.top;

        var d;
        if (Math.abs(y2 - y1) < 2) {
            d = 'M ' + x1 + ' ' + y1 + ' H ' + x2;
        } else {
            var midX = (x1 + x2) / 2;
            var r = Math.min(8, Math.abs(y2 - y1) / 2, Math.abs(x2 - x1) / 2);
            var dirY = y2 > y1 ? 1 : -1;
            d = 'M ' + x1 + ' ' + y1
                + ' H ' + (midX - r)
                + ' Q ' + midX + ' ' + y1 + ', ' + midX + ' ' + (y1 + dirY * r)
                + ' V ' + (y2 - dirY * r)
                + ' Q ' + midX + ' ' + y2 + ', ' + (midX + r) + ' ' + y2
                + ' H ' + x2;
        }

        var label = typeParts.fromSide === 'start'
            ? 'Start-to-Start (' + lane + ')'
            : 'End-to-Start (' + lane + ')';

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        path.setAttribute('stroke', lane === 'actual' ? '#16a34a' : '#6b7280');
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', 'url(#gantt-arrowhead)');
        path.setAttribute('class', 'gantt-dependency-arrow');
        path.setAttribute('data-task-id', taskId);
        path.setAttribute('data-predecessor-id', predecessorId);
        path.setAttribute('data-dependency-type', dependencyType);
        path.setAttribute('data-lane', lane);
        path.setAttribute('title', label);
        svg.appendChild(path);
    }

    function drawGanttDependencyArrows() {
        var svg = document.getElementById('gantt-dependency-arrows');
        var wrapper = document.getElementById('gantt-wrapper');
        var table = wrapper ? wrapper.querySelector('table') : null;
        if (!svg || !wrapper || !table) return;

        var wrapperRect = wrapper.getBoundingClientRect();
        svg.setAttribute('width', wrapper.scrollWidth);
        svg.setAttribute('height', wrapper.scrollHeight);
        svg.setAttribute('viewBox', '0 0 ' + wrapper.scrollWidth + ' ' + wrapper.scrollHeight);

        var existingPaths = svg.querySelectorAll('path');
        existingPaths.forEach(function(p) { p.remove(); });

        wrapper.querySelectorAll('.task-row[data-task-id]').forEach(function(row) {
            var taskId = row.dataset.taskId;
            var predecessorId = row.dataset.predecessorId;
            var dependencyType = row.dataset.dependencyType || 'end_to_start';
            if (!predecessorId) return;

            drawGanttDependencyArrowForLane(wrapper, svg, wrapperRect, taskId, predecessorId, dependencyType, 'plan');
            drawGanttDependencyArrowForLane(wrapper, svg, wrapperRect, taskId, predecessorId, dependencyType, 'actual');
        });
    }

    var originalGanttApplyZoom = ganttApplyZoom;
    ganttApplyZoom = function(zoomLevel) {
        originalGanttApplyZoom(zoomLevel);
        if (typeof requestAnimationFrame !== 'undefined') {
            requestAnimationFrame(function() { drawGanttDependencyArrows(); });
        } else {
            setTimeout(drawGanttDependencyArrows, 0);
        }
    };

    document.querySelectorAll('.phase-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            setTimeout(drawGanttDependencyArrows, 50);
        });
    });

    window.addEventListener('load', drawGanttDependencyArrows);
    window.addEventListener('resize', drawGanttDependencyArrows);

    if (typeof ganttEnterFullscreen === 'function' && typeof ganttExitFullscreen === 'function') {
        var originalGanttEnterFullscreen = ganttEnterFullscreen;
        var originalGanttExitFullscreen = ganttExitFullscreen;
        ganttEnterFullscreen = function() { originalGanttEnterFullscreen(); setTimeout(drawGanttDependencyArrows, 50); };
        ganttExitFullscreen = function() { originalGanttExitFullscreen(); setTimeout(drawGanttDependencyArrows, 50); };
    }

    // --- Drag-to-Create Dependency ---
    var ganttUpdateDependencyUrl = '{{ route("admin.project.projects.tasks.dependency.update", ["project" => $project->id, "task" => ":taskId"]) }}';
    var ganttDragState = { isDragging: false, startDot: null, tempPath: null };

    function ganttGetWrapperPoint(e) {
        var wrapper = document.getElementById('gantt-wrapper');
        var rect = wrapper.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    function ganttGetDotPoint(dot) {
        var wrapper = document.getElementById('gantt-wrapper');
        var rect = dot.getBoundingClientRect();
        var wrapperRect = wrapper.getBoundingClientRect();
        var x = dot.dataset.dotSide === 'left' ? (rect.left - wrapperRect.left) : (rect.right - wrapperRect.left);
        var y = (rect.top + rect.bottom) / 2 - wrapperRect.top;
        return { x: x, y: y };
    }

    function ganttCreateTempArrow(x1, y1, x2, y2) {
        var svg = document.getElementById('gantt-dependency-arrows');
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('class', 'gantt-drag-arrow');
        path.setAttribute('stroke', '#9ca3af');
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke-dasharray', '4 2');
        ganttUpdateTempArrow(path, x1, y1, x2, y2);
        svg.appendChild(path);
        return path;
    }

    function ganttUpdateTempArrow(path, x1, y1, x2, y2) {
        var d;
        if (Math.abs(y2 - y1) < 2) {
            d = 'M ' + x1 + ' ' + y1 + ' H ' + x2;
        } else {
            var midX = (x1 + x2) / 2;
            var r = Math.min(8, Math.abs(y2 - y1) / 2, Math.abs(x2 - x1) / 2);
            var dirY = y2 > y1 ? 1 : -1;
            d = 'M ' + x1 + ' ' + y1
                + ' H ' + (midX - r)
                + ' Q ' + midX + ' ' + y1 + ', ' + midX + ' ' + (y1 + dirY * r)
                + ' V ' + (y2 - dirY * r)
                + ' Q ' + midX + ' ' + y2 + ', ' + (midX + r) + ' ' + y2
                + ' H ' + x2;
        }
        path.setAttribute('d', d);
    }

    function ganttRemoveTempArrow() {
        if (ganttDragState.tempPath) {
            ganttDragState.tempPath.remove();
            ganttDragState.tempPath = null;
        }
    }

    document.querySelectorAll('.gantt-dot').forEach(function(dot) {
        dot.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var wrapper = document.getElementById('gantt-wrapper');
            var svg = document.getElementById('gantt-dependency-arrows');
            if (!wrapper || !svg) return;
            ganttDragState.isDragging = true;
            ganttDragState.startDot = dot;
            var startPoint = ganttGetDotPoint(dot);
            ganttDragState.tempPath = ganttCreateTempArrow(startPoint.x, startPoint.y, startPoint.x, startPoint.y);
        });
    });

    document.addEventListener('mousemove', function(e) {
        if (!ganttDragState.isDragging || !ganttDragState.tempPath) return;
        var startPoint = ganttGetDotPoint(ganttDragState.startDot);
        var endPoint = ganttGetWrapperPoint(e);
        ganttUpdateTempArrow(ganttDragState.tempPath, startPoint.x, startPoint.y, endPoint.x, endPoint.y);
    });

    document.addEventListener('mouseup', function(e) {
        if (!ganttDragState.isDragging) return;
        ganttDragState.isDragging = false;

        var startDot = ganttDragState.startDot;
        var target = document.elementFromPoint(e.clientX, e.clientY);

        if (target && target.classList && target.classList.contains('gantt-dot') && target !== startDot) {
            var startSide = startDot.dataset.dotSide;
            var endSide = target.dataset.dotSide;
            var startLane = startDot.dataset.barType;
            var endLane = target.dataset.barType;
            var dependencyType = null;

            // Only plan-plan or actual-actual dependencies are supported
            if (startLane && endLane && startLane === endLane && (startLane === 'plan' || startLane === 'actual')) {
                var sourceSide = startSide === 'left' ? 'start' : 'end';
                var targetSide = endSide === 'left' ? 'start' : 'end';

                // Valid combinations: end-to-start or start-to-start
                if (sourceSide === 'end' && targetSide === 'start') {
                    dependencyType = 'end_to_start';
                } else if (sourceSide === 'start' && targetSide === 'start') {
                    dependencyType = 'start_to_start';
                }
            }

            if (dependencyType) {
                var predecessorId = startDot.dataset.taskId;
                var successorId = target.dataset.taskId;

                if (predecessorId && successorId && predecessorId !== successorId) {
                    var successorRow = document.querySelector('.task-row[data-task-id="' + successorId + '"]');
                    if (successorRow && successorRow.dataset.predecessorId && successorRow.dataset.predecessorId !== predecessorId) {
                        if (!confirm('This task already has a predecessor. Replace it with the new dependency?')) {
                            ganttRemoveTempArrow();
                            ganttDragState.startDot = null;
                            return;
                        }
                    }

                    var url = ganttUpdateDependencyUrl.replace(':taskId', successorId);
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': gcmCsrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            predecessor_task_id: parseInt(predecessorId, 10),
                            dependency_type: dependencyType
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            if (successorRow) {
                                successorRow.dataset.predecessorId = predecessorId;
                                successorRow.dataset.dependencyType = dependencyType;
                            }
                            drawGanttDependencyArrows();
                        } else {
                            alert(data.message || 'Failed to create dependency.');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        alert('Failed to create dependency.');
                    });
                }
            } else {
                alert('Invalid dependency connection. Please connect appropriate ends.');
            }
        }

        ganttRemoveTempArrow();
        ganttDragState.startDot = null;
    });

    // Initial draw in case window.load already fired or is circumvented by SPA
    setTimeout(drawGanttDependencyArrows, 50);

    // --- Dependency Delete Confirmation ---
    var ganttDependencyToDelete = null;

    function ganttShowDependencyDeleteModal(taskId, predecessorId) {
        ganttDependencyToDelete = { taskId: taskId, predecessorId: predecessorId };
        var modal = document.getElementById('gantt-dependency-delete-modal');
        if (modal) {
            document.body.appendChild(modal);
            modal.classList.remove('hidden');
        }
        if (ganttDependencyArrowsSvg) {
            ganttDependencyArrowsSvg.style.pointerEvents = 'none';
        }
    }

    function ganttHideDependencyDeleteModal() {
        document.getElementById('gantt-dependency-delete-modal').classList.add('hidden');
        ganttDependencyToDelete = null;
        if (ganttDependencyArrowsSvg) {
            ganttDependencyArrowsSvg.style.pointerEvents = '';
        }
    }

    var ganttDependencyArrowsSvg = document.getElementById('gantt-dependency-arrows');
    if (ganttDependencyArrowsSvg) {
        ganttDependencyArrowsSvg.addEventListener('click', function(e) {
            var path = e.target.closest ? e.target.closest('path.gantt-dependency-arrow') : null;
            if (!path) return;
            var taskId = path.dataset.taskId;
            var predecessorId = path.dataset.predecessorId;
            if (taskId) ganttShowDependencyDeleteModal(taskId, predecessorId);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.closest('#gantt-dep-delete-cancel')) {
            e.preventDefault();
            ganttHideDependencyDeleteModal();
            return;
        }
        if (e.target.closest('#gantt-dep-delete-confirm')) {
            e.preventDefault();
            if (!ganttDependencyToDelete) return;
            var taskId = ganttDependencyToDelete.taskId;
            var url = ganttUpdateDependencyUrl.replace(':taskId', taskId);
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': gcmCsrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    predecessor_task_id: null,
                    dependency_type: 'end_to_start'
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var row = document.querySelector('.task-row[data-task-id="' + taskId + '"]');
                    if (row) {
                        row.dataset.predecessorId = '';
                        row.dataset.dependencyType = 'end_to_start';
                    }
                    drawGanttDependencyArrows();
                } else {
                    alert(data.message || 'Failed to delete dependency.');
                }
                ganttHideDependencyDeleteModal();
            })
            .catch(function(err) {
                console.error(err);
                alert('Failed to delete dependency.');
                ganttHideDependencyDeleteModal();
            });
        }
    });

    // Gantt task row drag-and-drop reorder
    var ganttDraggedRow = null;
    var ganttDraggedOrder = null;
    var ganttDraggedPhaseId = null;
    var ganttDraggedUpdateUrl = null;
    var ganttLastOverRow = null;

    document.querySelectorAll('.gantt-drag-handle').forEach(function(handle) {
        handle.draggable = true;
        handle.addEventListener('dragstart', function(e) {
            e.stopPropagation();
            var row = this.closest('.gantt-draggable-row');
            if (!row) return;
            ganttDraggedRow = row;
            ganttDraggedOrder = parseInt(row.dataset.taskOrder, 10);
            ganttDraggedPhaseId = row.dataset.phaseId;
            ganttDraggedUpdateUrl = row.dataset.updateUrl;
            row.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.taskId);
        });
        handle.addEventListener('dragend', function(e) {
            e.stopPropagation();
            if (ganttDraggedRow) ganttDraggedRow.classList.remove('opacity-50');
            document.querySelectorAll('.gantt-draggable-row').forEach(function(r) {
                r.style.borderTop = '';
                r.style.borderBottom = '';
            });
            ganttDraggedRow = null;
            ganttDraggedOrder = null;
            ganttDraggedPhaseId = null;
            ganttDraggedUpdateUrl = null;
            ganttLastOverRow = null;
        });
    });

    document.querySelectorAll('.gantt-draggable-row').forEach(function(row) {
        row.addEventListener('dragover', function(e) {
            if (!ganttDraggedRow || this === ganttDraggedRow) return;
            if (ganttDraggedPhaseId !== this.dataset.phaseId) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var rect = this.getBoundingClientRect();
            var mid = rect.top + rect.height / 2;
            if (ganttLastOverRow && ganttLastOverRow !== this) {
                ganttLastOverRow.style.borderTop = '';
                ganttLastOverRow.style.borderBottom = '';
            }
            ganttLastOverRow = this;
            if (e.clientY < mid) {
                this.style.borderTop = '2px solid #4f46e5';
                this.style.borderBottom = '';
            } else {
                this.style.borderTop = '';
                this.style.borderBottom = '2px solid #4f46e5';
            }
        });

        row.addEventListener('dragleave', function(e) {
            this.style.borderTop = '';
            this.style.borderBottom = '';
        });

        row.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!ganttDraggedRow || this === ganttDraggedRow) return;
            if (ganttDraggedPhaseId !== this.dataset.phaseId) {
                alert('Tasks can only be reordered within the same phase.');
                return;
            }
            var rect = this.getBoundingClientRect();
            var after = e.clientY >= rect.top + rect.height / 2;
            var targetOrder = parseInt(this.dataset.taskOrder, 10);
            var newOrder;

            if (after) {
                newOrder = targetOrder + (ganttDraggedOrder > targetOrder ? 1 : 0);
            } else {
                var siblings = Array.from(this.parentElement.children).filter(function(c) {
                    return c.classList.contains('gantt-draggable-row') && c !== ganttDraggedRow;
                });
                var targetIndex = siblings.indexOf(this);
                var prevRow = targetIndex > 0 ? siblings[targetIndex - 1] : null;
                if (prevRow) {
                    var prevOrder = parseInt(prevRow.dataset.taskOrder, 10);
                    newOrder = prevOrder + (ganttDraggedOrder > prevOrder ? 1 : 0);
                } else {
                    newOrder = 1;
                }
            }

            if (newOrder !== ganttDraggedOrder && ganttDraggedUpdateUrl) {
                var formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('task_order', newOrder);
                fetch(ganttDraggedUpdateUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        window.location.reload();
                    } else {
                        alert(data && data.message ? data.message : 'Reordering failed.');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    alert('Reordering failed.');
                });
            }
        });
    });

    // --- Gantt Bar/Dependency Visibility Toggle ---
    (function() {
        var storageKey = 'gantt-visibility';
        var defaultVisibility = { plan: true, revise: true, actual: true, dependencies: true };
        var stored = localStorage.getItem(storageKey);
        var visibility = Object.assign({}, defaultVisibility, stored ? JSON.parse(stored) : {});

        function applyVisibility() {
            ['plan', 'revise', 'actual'].forEach(function(type) {
                var show = !!visibility[type];
                document.querySelectorAll('.gantt-bar[data-bar-type="' + type + '"], .gantt-lane[data-bar-type="' + type + '"]').forEach(function(el) {
                    el.classList.toggle('hidden', !show);
                });
            });
            document.querySelectorAll('.gantt-effective-bar').forEach(function(el) {
                el.classList.toggle('hidden', !visibility.actual);
            });
            var arrowSvg = document.getElementById('gantt-dependency-arrows');
            if (arrowSvg) {
                arrowSvg.classList.toggle('hidden', !visibility.dependencies);
            }
        }

        function saveVisibility() {
            localStorage.setItem(storageKey, JSON.stringify(visibility));
        }

        var displayBtn = document.getElementById('gantt-display-btn');
        var displayMenu = document.getElementById('gantt-display-menu');
        if (displayBtn && displayMenu) {
            displayBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                displayMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function(e) {
                if (!displayMenu.contains(e.target) && !displayBtn.contains(e.target)) {
                    displayMenu.classList.add('hidden');
                }
            });
        }

        document.querySelectorAll('.gantt-visibility-toggle').forEach(function(toggle) {
            var type = toggle.dataset.target;
            if (type) {
                toggle.checked = !!visibility[type];
            }
            toggle.addEventListener('change', function() {
                var target = this.dataset.target;
                if (!target) return;
                visibility[target] = this.checked;
                saveVisibility();
                applyVisibility();
                if (['plan', 'revise', 'actual'].indexOf(target) !== -1 && typeof drawGanttDependencyArrows === 'function') {
                    drawGanttDependencyArrows();
                }
            });
        });

        window.addEventListener('load', function() {
            applyVisibility();
            if (typeof drawGanttDependencyArrows === 'function') {
                drawGanttDependencyArrows();
            }
        });
    })();

    // --- Gantt Export Button Visibility Inheritance ---
    (function() {
        var storageKey = 'gantt-visibility';
        var defaultVisibility = { plan: true, revise: true, actual: true, dependencies: true };

        function getVisibility() {
            var stored = localStorage.getItem(storageKey);
            return Object.assign({}, defaultVisibility, stored ? JSON.parse(stored) : {});
        }

        function buildVisibilityQuery() {
            var visibility = getVisibility();
            var parts = [];
            parts.push('plan=' + (visibility.plan ? '1' : '0'));
            parts.push('revise=' + (visibility.revise ? '1' : '0'));
            parts.push('actual=' + (visibility.actual ? '1' : '0'));
            parts.push('dependencies=' + (visibility.dependencies ? '1' : '0'));
            return parts.join('&');
        }

        var excelBtn = document.getElementById('gantt-export-excel-btn');

        if (excelBtn) {
            excelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var sep = excelBtn.href.indexOf('?') !== -1 ? '&' : '?';
                var zoom = window.ganttCurrentZoom || 'day';
                window.location.href = excelBtn.href + sep + buildVisibilityQuery() + '&zoom=' + encodeURIComponent(zoom);
            });
        }
    })();

    // --- Gantt Bar Resize and Progress Popup ---
    (function() {
        var ganttResizing = null;
        var ganttPending = [];
        var ganttProgressData = null;

        function ganttGetTimelineInfo() {
            var header = document.getElementById('gantt-timeline-header');
            var zoom = window.ganttCurrentZoom || 'day';
            var config = GANTT_ZOOM[zoom] || GANTT_ZOOM.day;
            return {
                timelineStart: header ? header.dataset.timelineStart : null,
                pixelsPerDay: config.pixelsPerDay,
                totalDays: header ? parseInt(header.dataset.totalDays, 10) : 0
            };
        }

        function ganttDateFromOffset(offset) {
            var info = ganttGetTimelineInfo();
            if (!info.timelineStart) return null;
            var parts = info.timelineStart.split('-');
            var date = new Date(parts[0], parts[1] - 1, parts[2]);
            date.setDate(date.getDate() + Math.round(offset));
            var y = date.getFullYear();
            var m = ('0' + (date.getMonth() + 1)).slice(-2);
            var d = ('0' + date.getDate()).slice(-2);
            return y + '-' + m + '-' + d;
        }

        function ganttGetAreaX(clientX) {
            var bar = ganttResizing ? ganttResizing.bar : null;
            var area = bar && bar.closest ? bar.closest('.gantt-timeline-area') : document.querySelector('.gantt-timeline-area');
            if (!area) return 0;
            var rect = area.getBoundingClientRect();
            return clientX - rect.left;
        }

        function ganttUpdateBarPreview(bar, newStartOffset, newDuration, pixelsPerDay) {
            bar.style.left = (newStartOffset * pixelsPerDay) + 'px';
            bar.style.width = (newDuration * pixelsPerDay) + 'px';
        }

        function ganttUpdatePendingActionsUi() {
            var panel = document.getElementById('gantt-pending-actions');
            var label = document.getElementById('gantt-pending-text');
            if (!panel) return;
            var count = ganttPending.length;
            if (count === 0) {
                panel.classList.add('hidden');
                return;
            }
            if (label) {
                label.textContent = count + ' unsaved change' + (count === 1 ? '' : 's');
            }
            panel.classList.remove('hidden');
        }

        function ganttHidePendingActions() {
            var panel = document.getElementById('gantt-pending-actions');
            if (panel) panel.classList.add('hidden');
        }

        function ganttPendingKey(p) {
            return p.taskId + '-' + p.barType + '-' + (p.side || 'move');
        }

        function ganttRevertBar() {
            ganttPending.forEach(function(p) {
                var bar = p.bar;
                if (!bar) return;
                bar.style.left = p.originalLeft;
                bar.style.width = p.originalWidth;
                bar.dataset.startOffset = p.originalStartOffset;
                bar.dataset.duration = p.originalDuration;
            });
            ganttPending = [];
            ganttUpdatePendingActionsUi();
        }

        function ganttDiscardAll() {
            if (ganttPending.length === 0) return;
            if (!confirm('Discard all unsaved changes?')) return;
            window.location.reload();
        }

        document.querySelectorAll('.gantt-resize-handle').forEach(function(handle) {
            handle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var bar = handle.closest ? handle.closest('.gantt-bar') : null;
                if (!bar) return;
                var info = ganttGetTimelineInfo();
                ganttResizing = {
                    bar: bar,
                    side: handle.dataset.resizeSide,
                    startX: ganttGetAreaX(e.clientX),
                    startOffset: parseFloat(bar.dataset.startOffset) || 0,
                    duration: parseFloat(bar.dataset.duration) || 1,
                    originalLeft: bar.style.left,
                    originalWidth: bar.style.width,
                    originalStartOffset: bar.dataset.startOffset,
                    originalDuration: bar.dataset.duration,
                    pixelsPerDay: info.pixelsPerDay,
                    moved: false,
                    currentStart: parseFloat(bar.dataset.startOffset) || 0,
                    currentDuration: parseFloat(bar.dataset.duration) || 1
                };
            });
        });

        document.addEventListener('mousemove', function(e) {
            if (!ganttResizing) return;
            var x = ganttGetAreaX(e.clientX);
            var delta = x - ganttResizing.startX;
            var pixelsPerDay = ganttResizing.pixelsPerDay || ganttGetTimelineInfo().pixelsPerDay;
            var dayDelta = Math.round(delta / pixelsPerDay);
            if (Math.abs(dayDelta) > 0) ganttResizing.moved = true;

            var startOffset = ganttResizing.startOffset;
            var duration = ganttResizing.duration;
            var newStart = startOffset;
            var newDuration = duration;

            if (ganttResizing.side === 'left') {
                newStart = Math.max(0, startOffset + dayDelta);
                newDuration = Math.max(1, startOffset + duration - newStart);
            } else {
                newDuration = Math.max(1, duration + dayDelta);
            }

            ganttResizing.currentStart = newStart;
            ganttResizing.currentDuration = newDuration;
            ganttUpdateBarPreview(ganttResizing.bar, newStart, newDuration, pixelsPerDay);
        });

        document.addEventListener('mouseup', function(e) {
            if (!ganttResizing) return;
            if (!ganttResizing.moved) {
                ganttResizing.bar.style.left = ganttResizing.originalLeft;
                ganttResizing.bar.style.width = ganttResizing.originalWidth;
                ganttResizing = null;
                return;
            }

            var bar = ganttResizing.bar;
            var startOffset = ganttResizing.currentStart;
            var duration = ganttResizing.currentDuration;
            var endOffset = startOffset + duration - 1;
            var startDate = ganttDateFromOffset(startOffset);
            var endDate = ganttDateFromOffset(endOffset);
            var barType = bar.dataset.barType;
            var taskId = bar.dataset.taskId;
            var row = document.querySelector('.task-row[data-task-id="' + taskId + '"]');

            var newPending = {
                taskId: taskId,
                bar: bar,
                barType: barType,
                side: ganttResizing.side,
                startDate: startDate,
                endDate: endDate,
                startOffset: startOffset,
                duration: duration,
                originalLeft: ganttResizing.originalLeft,
                originalWidth: ganttResizing.originalWidth,
                originalStartOffset: ganttResizing.originalStartOffset,
                originalDuration: ganttResizing.originalDuration,
                updateUrl: row ? row.dataset.updateUrl : ''
            };

            var key = ganttPendingKey(newPending);
            var existingIndex = ganttPending.findIndex(function(p) { return ganttPendingKey(p) === key; });
            if (existingIndex >= 0) {
                // Replace existing queued change with the latest one, but keep original values
                newPending.originalLeft = ganttPending[existingIndex].originalLeft;
                newPending.originalWidth = ganttPending[existingIndex].originalWidth;
                newPending.originalStartOffset = ganttPending[existingIndex].originalStartOffset;
                newPending.originalDuration = ganttPending[existingIndex].originalDuration;
                ganttPending[existingIndex] = newPending;
            } else {
                ganttPending.push(newPending);
            }

            bar.dataset.startOffset = startOffset;
            bar.dataset.duration = duration;
            ganttUpdatePendingActionsUi();
            ganttResizing = null;
        });

        var ganttPendingDiscard = document.getElementById('gantt-pending-discard');
        if (ganttPendingDiscard) {
            ganttPendingDiscard.addEventListener('click', function() {
                ganttDiscardAll();
            });
        }

        var ganttPendingSave = document.getElementById('gantt-pending-save');
        if (ganttPendingSave) {
            ganttPendingSave.addEventListener('click', function() {
                if (ganttPending.length === 0) return;

                var queue = ganttPending.slice();
                var results = [];
                var failed = false;

                function sendNext(index) {
                    if (index >= queue.length) {
                        if (failed) {
                            alert('Some changes failed to save. Please refresh the page.');
                        } else {
                            window.location.reload();
                        }
                        return;
                    }
                    var p = queue[index];
                    var body = {};
                    if (p.barType === 'plan') {
                        body.start_date_plan = p.startDate;
                        body.end_date_plan = p.endDate;
                    } else if (p.barType === 'revise') {
                        body.start_date_revise = p.startDate;
                        body.end_date_revise = p.endDate;
                    } else if (p.barType === 'actual') {
                        if (p.side === 'left') {
                            body.start_date_actual = p.startDate;
                        } else if (p.side === 'right') {
                            body.end_date_actual = p.endDate;
                        }
                    }
                    fetch(p.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': gcmCsrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(body)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            results.push(data);
                            sendNext(index + 1);
                        } else {
                            failed = true;
                            alert(data.message || 'Failed to save one of the changes.');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        failed = true;
                        alert('Failed to save one of the changes.');
                    });
                }

                sendNext(0);
            });
        }

        // Click on actual bar opens progress popup
        document.querySelectorAll('.gantt-effective-bar').forEach(function(bar) {
            bar.addEventListener('click', function(e) {
                if (e.target.closest && e.target.closest('.gantt-dot, .gantt-resize-handle')) return;
                var taskId = bar.dataset.taskId;
                var row = document.querySelector('.task-row[data-task-id="' + taskId + '"]');
                var progressText = bar.querySelector('.gantt-progress-text');
                var currentProgress = parseInt(progressText ? progressText.textContent : '0', 10) || 0;
                ganttProgressData = {
                    taskId: taskId,
                    updateUrl: row ? row.dataset.updateUrl : '',
                    currentProgress: currentProgress
                };
                var modal = document.getElementById('gantt-progress-modal');
                var slider = document.getElementById('gantt-progress-slider');
                var value = document.getElementById('gantt-progress-value');
                var big = document.getElementById('gantt-progress-big');
                var info = document.getElementById('gantt-progress-complete-info');
                if (slider) slider.value = currentProgress;
                if (value) value.textContent = currentProgress + '%';
                if (big) big.textContent = currentProgress + '%';
                if (info) info.classList.add('hidden');
                if (modal) modal.classList.remove('hidden');
            });
        });

        window.addEventListener('load', function() {
            var ganttProgressSlider = document.getElementById('gantt-progress-slider');
            if (ganttProgressSlider) {
                ganttProgressSlider.addEventListener('input', function() {
                    var value = document.getElementById('gantt-progress-value');
                    var big = document.getElementById('gantt-progress-big');
                    var info = document.getElementById('gantt-progress-complete-info');
                    if (value) value.textContent = this.value + '%';
                    if (big) big.textContent = this.value + '%';
                    if (info) info.classList.add('hidden');
                });
            }

            var ganttProgressComplete = document.getElementById('gantt-progress-complete');
            if (ganttProgressComplete) {
                ganttProgressComplete.addEventListener('click', function() {
                    var slider = document.getElementById('gantt-progress-slider');
                    var value = document.getElementById('gantt-progress-value');
                    var big = document.getElementById('gantt-progress-big');
                    var info = document.getElementById('gantt-progress-complete-info');
                    if (slider) slider.value = 100;
                    if (value) value.textContent = '100%';
                    if (big) big.textContent = '100%';
                    if (info) info.classList.remove('hidden');
                });
            }

            var ganttProgressCancel = document.getElementById('gantt-progress-cancel');
            if (ganttProgressCancel) {
                ganttProgressCancel.addEventListener('click', function() {
                    var modal = document.getElementById('gantt-progress-modal');
                    if (modal) modal.classList.add('hidden');
                    ganttProgressData = null;
                });
            }

            var ganttProgressSave = document.getElementById('gantt-progress-save');
            if (ganttProgressSave) {
                ganttProgressSave.addEventListener('click', function() {
                    if (!ganttProgressData) return;
                    if (ganttPending.length > 0) {
                        if (!confirm('You have unsaved bar drag changes. Saving progress will reload the page and discard them. Continue?')) return;
                    }
                    var slider = document.getElementById('gantt-progress-slider');
                    var progress = slider ? parseInt(slider.value, 10) : ganttProgressData.currentProgress;
                    var body = {
                        progress_actual: progress,
                        status: progress >= 100 ? 'completed' : 'in_progress'
                    };
                    if (progress >= 100) {
                        var today = new Date();
                        body.end_date_actual = today.toISOString().split('T')[0];
                    }
                    fetch(ganttProgressData.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': gcmCsrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(body)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to save progress.');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        alert('Failed to save progress.');
                    });
                });
            }
        });

        // --- Recent Gantt Changes ---
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.getElementById('gantt-chart-container');
            var url = container ? container.dataset.recentChangesUrl : '';
            var list = document.getElementById('gantt-changes-list');
            var count = document.getElementById('gantt-changes-count');
            var page = document.getElementById('gantt-changes-page');
            var prevBtn = document.getElementById('gantt-changes-prev');
            var nextBtn = document.getElementById('gantt-changes-next');
            var currentPage = 1;

            function loadChanges(pageNum) {
                if (!url || !list) return;
                list.innerHTML = '<p class="text-xs text-gray-500 py-2">Loading recent changes...</p>';
                var sep = url.indexOf('?') >= 0 ? '&' : '?';
                fetch(url + sep + 'page=' + pageNum, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.data) return;
                    currentPage = data.pagination.current_page;
                    if (count) {
                        count.textContent = data.pagination.total + ' total change' + (data.pagination.total === 1 ? '' : 's');
                    }
                    if (page) {
                        page.textContent = 'Page ' + currentPage + ' of ' + data.pagination.last_page;
                    }
                    if (prevBtn) prevBtn.disabled = currentPage <= 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= data.pagination.last_page;

                    if (data.data.length === 0) {
                        list.innerHTML = '<p class="text-xs text-gray-500 py-2">No recent changes.</p>';
                        return;
                    }

                    var html = '<div class="space-y-2">';
                    data.data.forEach(function(item) {
                        html += '<div class="flex items-start justify-between gap-3 py-1 border-b border-gray-100 last:border-0">' +
                            '<div class="min-w-0">' +
                                '<p class="text-xs text-gray-800 truncate">' + escapeHtml(item.description) + '</p>' +
                                '<p class="text-[10px] text-gray-500">by ' + escapeHtml(item.user) + ' &bull; ' + escapeHtml(item.created_at) + '</p>' +
                            '</div>' +
                            '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 whitespace-nowrap">' + escapeHtml(item.action) + '</span>' +
                        '</div>';
                    });
                    html += '</div>';
                    list.innerHTML = html;
                })
                .catch(function(err) {
                    console.error(err);
                    if (list) list.innerHTML = '<p class="text-xs text-red-500 py-2">Failed to load recent changes.</p>';
                });
            }

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (currentPage > 1) loadChanges(currentPage - 1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    loadChanges(currentPage + 1);
                });
            }

            loadChanges(1);
        });
    })();

</script>

{{-- Dependency Delete Confirmation Modal --}}
<div id="gantt-dependency-delete-modal" class="hidden fixed inset-0 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5); pointer-events: auto; z-index: 9999;">
    <div class="bg-white rounded-lg shadow-xl w-80 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-800">Delete Dependency?</h4>
        </div>
        <div class="px-4 py-3">
            <p class="text-xs text-gray-600">Remove this dependency link?</p>
        </div>
        <div class="px-4 py-3 bg-gray-50 flex justify-end gap-2">
            <button id="gantt-dep-delete-cancel" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">Cancel</button>
            <button id="gantt-dep-delete-confirm" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition">Delete</button>
        </div>
    </div>
</div>

{{-- Progress Update Modal --}}
<div id="gantt-progress-modal" class="hidden fixed inset-0 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5); pointer-events: auto; z-index: 9999;">
    <div class="bg-white rounded-lg shadow-xl w-80 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-800">Update Progress</h4>
        </div>
        <div class="px-4 py-3">
            <div class="text-center mb-2">
                <span id="gantt-progress-big" class="text-2xl font-bold text-indigo-600">0%</span>
            </div>
            <input type="range" id="gantt-progress-slider" min="0" max="100" step="1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer mb-3">
            <div class="flex items-center justify-between text-xs text-gray-600">
                <span>0%</span>
                <span id="gantt-progress-value" class="font-semibold">0%</span>
                <span>100%</span>
            </div>
            <div id="gantt-progress-complete-info" class="hidden mt-2 text-xs text-green-700">Completing will set the end date to today.</div>
        </div>
        <div class="px-4 py-3 bg-gray-50 flex justify-between gap-2">
            <button id="gantt-progress-complete" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition">Complete</button>
            <div class="flex gap-2">
                <button id="gantt-progress-cancel" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">Cancel</button>
                <button id="gantt-progress-save" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Recent Gantt Changes Box --}}
<div class="bg-white border border-gray-200 rounded-lg shadow-sm mt-4 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <h4 class="text-sm font-semibold text-gray-800">Recent Changes</h4>
        <span id="gantt-changes-count" class="text-xs text-gray-500">Loading...</span>
    </div>
    <div id="gantt-changes-list" class="px-4 py-2">
        <p class="text-xs text-gray-500 py-2">Loading recent changes...</p>
    </div>
    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
        <button id="gantt-changes-prev" type="button" class="px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed">&larr; Prev</button>
        <span id="gantt-changes-page" class="text-xs text-gray-500">Page 1</span>
        <button id="gantt-changes-next" type="button" class="px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed">Next &rarr;</button>
    </div>
</div>
</div>
