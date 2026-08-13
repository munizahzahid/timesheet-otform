@php
    // Get all phases with their tasks
    $phases = $project->phases()->with(['tasks' => function($q) {
        $q->with(['assignedTo', 'predecessorTask'])->orderBy('task_order');
    }])->orderBy('phase_order')->get();

    // Get tasks without a phase (standalone tasks)
    $standaloneTasks = $project->tasks()->whereNull('phase_id')->with(['assignedTo', 'predecessorTask'])->orderBy('task_order')->get();

    // All subtasks for dependency selection
    $allSubtasks = $project->tasks()->orderBy('task_order')->get();

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

<div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">

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
        <h3 class="text-base font-semibold text-gray-800">Project Schedule / Gantt Chart</h3>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-5 text-xs mr-4">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-blue-500 rounded shadow-sm"></div>
                    <span class="text-gray-700 font-medium text-[11px]">Plan</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-orange-500 rounded shadow-sm"></div>
                    <span class="text-gray-700 font-medium text-[11px]">Revise</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-emerald-500 rounded shadow-sm"></div>
                    <span class="text-gray-700 font-medium text-[11px]">Actual</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-emerald-400 rounded border border-emerald-500 border-dashed shadow-sm"></div>
                    <span class="text-gray-700 font-medium text-[11px]">Effective</span>
                </div>
            </div>
            <div class="flex items-center gap-1 mr-2">
                <div class="relative" id="gantt-zoom-toggle">
                    <button type="button" id="gantt-zoom-trigger" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition">
                        <span id="gantt-zoom-trigger-label" class="w-10 text-center">Day</span>
                        <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="gantt-zoom-menu" class="hidden absolute right-0 mt-1 w-20 bg-white border border-gray-200 rounded-md shadow-lg z-50 p-1">
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition" data-zoom="day">Day</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition" data-zoom="week">Week</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition" data-zoom="month">Month</button>
                        <button type="button" class="gantt-zoom-btn w-full text-left px-2 py-1 text-[10px] font-medium rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition" data-zoom="year">Year</button>
                    </div>
                </div>
            </div>
            <button type="button" id="gantt-fullscreen-btn" class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:shadow-sm transition mr-2" title="Fullscreen">
                <svg id="gantt-icon-expand" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                <svg id="gantt-icon-contract" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4M4 10V6m0 0h4M4 6l5 5m11-5l-5 5m5-5V6m0 0h-4"/>
                </svg>
            </button>
            <div class="relative mr-2" id="gantt-display-toggle">
                <button type="button" id="gantt-display-btn" class="inline-flex items-center gap-1.5 px-2 py-1 bg-white border border-gray-200 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-wider hover:bg-gray-50 hover:shadow-sm transition">
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
            <a id="gantt-export-excel-btn" href="{{ route('project.projects.tasks.gantt-export-excel', $project) }}"
               class="inline-flex items-center px-2 py-1 bg-green-600 border border-transparent rounded-md font-semibold text-[10px] text-white uppercase tracking-wider hover:bg-green-700 transition">
                Download Excel
            </a>
            <button type="button" onclick="openTaskCreateModal()"
                    class="inline-flex items-center px-2 py-1 bg-indigo-600 border border-transparent rounded-md font-semibold text-[10px] text-white uppercase tracking-wider hover:bg-indigo-700 transition">
                Add Subtask
            </button>
            <button type="button" onclick="openSubtaskCreateModal()"
                    class="inline-flex items-center px-2 py-1 bg-white border border-gray-200 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-wider hover:bg-gray-50 hover:shadow-sm transition">
                Add Subtask
            </button>
        </div>
    </div>
    <div id="gantt-pending-actions" class="hidden px-6 py-2 bg-yellow-50 border-b border-yellow-100 flex items-center justify-between">
        <span id="gantt-pending-text" class="text-xs text-yellow-800 font-medium">No unsaved changes</span>
        <div class="flex items-center gap-2">
            <button id="gantt-pending-discard" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 hover:shadow-sm transition">Discard All</button>
            <button id="gantt-pending-save" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition">Save All</button>
        </div>
    </div>

    @if($phases->isEmpty() && $standaloneTasks->isEmpty())
        <div class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            <p class="text-sm text-gray-500 mt-2">No tasks or subtasks yet.</p>
            <p class="text-xs text-gray-400 mt-1">Add tasks and subtasks to see the Gantt chart.</p>
        </div>
    @else
        <div id="gantt-chart-container" class="relative" data-recent-changes-url="{{ route('project.projects.tasks.gantt-changes', $project) }}">
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
                            Task/Subtask
                        </th>
                        <th class="sticky left-64 bg-gray-50 z-40 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-24">
                            Assigned To
                        </th>
                        <th class="bg-gray-50 px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-16">
                            Weight
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
                        {{-- Task Row (was Phase) --}}
                        <tr class="bg-gray-100 phase-row" data-phase-id="{{ $phase->id }}">
                            <td class="sticky left-0 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            class="phase-toggle-btn text-gray-500 hover:text-gray-700 focus:outline-none"
                                            data-phase-id="{{ $phase->id }}"
                                            title="Toggle subtasks">
                                        <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div class="gantt-menu">
                                        <button type="button" class="gantt-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100" title="Task actions"
                                                data-edit-type="phase"
                                                data-edit-id="{{ $phase->id }}"
                                                data-update-url="{{ route('project.projects.phases.update', [$project, $phase]) }}"
                                                data-delete-action="{{ route('project.projects.phases.destroy', [$project, $phase]) . '?' . (request()->getQueryString() ?: 'tab=schedule') }}"
                                                data-delete-confirm="Delete this task and all its subtasks?"
                                                data-phase-name="{{ $phase->phase_name }}"
                                                data-phase-order="{{ $phase->phase_order }}"
                                                data-start-date-plan="{{ $phase->start_date_plan?->format('Y-m-d') ?? '' }}"
                                                data-end-date-plan="{{ $phase->end_date_plan?->format('Y-m-d') ?? '' }}"
                                                data-start-date-actual="{{ $phase->start_date_actual?->format('Y-m-d') ?? '' }}"
                                                data-end-date-actual="{{ $phase->end_date_actual?->format('Y-m-d') ?? '' }}"
                                                data-start-date-revise="{{ $phase->start_date_revise?->format('Y-m-d') ?? '' }}"
                                                data-end-date-revise="{{ $phase->end_date_revise?->format('Y-m-d') ?? '' }}">
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
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500 text-center w-16">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
                                <div class="space-y-0.5">
                                    <div class="text-blue-600">P: {{ $phase->progress_plan }}%</div>
                                    @if($phase->delay_days > 0)
                                        <div class="text-red-600 font-semibold whitespace-nowrap" title="Progress delay: actual progress is behind plan progress by {{ $phase->delay_days }} day(s)">
                                            {{ $phase->delay_days }}d behind
                                        </div>
                                    @endif
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
                                        <div class="gantt-grid-line absolute" data-day-offset="{{ $i }}" data-day-date="{{ $timelineStart->copy()->addDays($i)->format('Y-m-d') }}" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
                                    @endfor
                                    {{-- Plan bar --}}
                                    @if($phasePlanStartOffset !== null && $phasePlanDuration !== null)
                                        <div class="gantt-bar absolute" data-start-offset="{{ $phasePlanStartOffset }}" data-duration="{{ $phasePlanDuration }}" data-bar-type="plan"
                                             style="left: {{ $phasePlanStartOffset * $dayWidth }}px; top: 8px; width: {{ max($phasePlanDuration * $dayWidth, 4) }}px; height: 16px; background-color: rgba(168, 85, 247, 0.3); border: 1px solid #9333ea; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                             title="Plan: {{ $phase->start_date_plan->format('d M Y') }} — {{ $phase->end_date_plan->format('d M Y') }}">
                                            {{-- Progress fill --}}
                                            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $phase->progress_plan }}%; background-color: #a855f7; transition: width 0.3s ease;"></div>
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
                                             style="left: {{ $phaseReviseStartOffset * $dayWidth }}px; top: 28px; width: {{ max($phaseReviseDuration * $dayWidth, 4) }}px; height: 16px; background-color: rgba(251, 146, 60, 0.3); border: 1px solid #f97316; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                             title="Revise: {{ $phase->start_date_revise->format('d M Y') }} — {{ $phase->end_date_revise->format('d M Y') }}">
                                            {{-- Progress fill --}}
                                            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $reviseProgress }}%; background-color: #fb923c; transition: width 0.3s ease;"></div>
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

                        {{-- Task Subtasks --}}
                        @foreach($phase->tasks as $task)
                            @include('admin.project.partials._gantt_task_row', ['task' => $task, 'timelineStart' => $timelineStart, 'totalDays' => $totalDays, 'dayWidth' => $dayWidth, 'effectiveDates' => $effectiveDates, 'todayOffset' => $todayOffset])
                        @endforeach
                    @endforeach

                    {{-- Standalone Subtasks --}}
                    @if($standaloneTasks->isNotEmpty())
                        <tr class="bg-gray-100">
                            <td class="sticky left-0 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200">
                                <span class="font-semibold text-gray-900">Standalone Subtasks</span>
                            </td>
                            <td class="sticky left-64 bg-gray-100 z-40 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500 text-center w-16">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="bg-gray-100 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="px-0 py-2 bg-gray-100">
                                <div class="gantt-timeline-area relative" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
                                    @for($i = 0; $i <= $totalDays; $i++)
                                        <div class="gantt-grid-line absolute" data-day-offset="{{ $i }}" data-day-date="{{ $timelineStart->copy()->addDays($i)->format('Y-m-d') }}" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
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
                    <marker id="gantt-arrowhead-plan" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto" markerUnits="strokeWidth">
                        <path d="M0,0 L0,12 L10,6 z" fill="#4b5563"/>
                    </marker>
                    <marker id="gantt-arrowhead-revise" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto" markerUnits="strokeWidth">
                        <path d="M0,0 L0,12 L10,6 z" fill="#c2410c"/>
                    </marker>
                    <marker id="gantt-arrowhead-actual" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto" markerUnits="strokeWidth">
                        <path d="M0,0 L0,12 L10,6 z" fill="#059669"/>
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

{{-- Subtask Quick Update Modal --}}
<div id="task-quick-update-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-96 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Update Subtask</h4>
            <button type="button" onclick="closeTaskUpdateModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="task-quick-update-form" method="POST" class="p-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Subtask</label>
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
                    Save Subtask
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Subtask Create Modal --}}
<div id="task-create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-[420px] max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Create Subtask</h4>
            <button type="button" onclick="closeTaskCreateModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="task-create-form" method="POST" action="{{ route('project.projects.phases.store', $project) }}" class="p-4 space-y-4">
            @csrf
            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
            <div>
                <label for="modal_phase_name" class="block text-xs font-medium text-gray-700 mb-1">Task Name <span class="text-red-500">*</span></label>
                <input type="text" name="phase_name" id="modal_phase_name" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_name"></p>
            </div>
            <div>
                <label for="modal_phase_order" class="block text-xs font-medium text-gray-700 mb-1">Task Order <span class="text-red-500">*</span></label>
                <input type="number" name="phase_order" id="modal_phase_order" min="1" value="1" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_order"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_task_start_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan Start</label>
                    <input type="date" name="start_date_plan" id="modal_task_start_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="start_date_plan"></p>
                </div>
                <div>
                    <label for="modal_task_end_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan End</label>
                    <input type="date" name="end_date_plan" id="modal_task_end_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="end_date_plan"></p>
                </div>
            </div>
            <div id="task-create-general-error" class="hidden p-2 bg-red-50 text-xs text-red-700 rounded"></div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeTaskCreateModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">Create Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Subtask Create Modal --}}
<div id="subtask-create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-[480px] max-w-full mx-4 overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Create Subtask</h4>
            <button type="button" onclick="closeSubtaskCreateModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="subtask-create-form" method="POST" action="{{ route('project.projects.tasks.store', $project) }}" class="p-4 space-y-4">
            @csrf
            <input type="hidden" name="tab" value="schedule">
            <input type="hidden" name="view" value="">
            <div>
                <label for="modal_subtask_name" class="block text-xs font-medium text-gray-700 mb-1">Subtask Name <span class="text-red-500">*</span></label>
                <input type="text" name="task_name" id="modal_subtask_name" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="task_name"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_subtask_order" class="block text-xs font-medium text-gray-700 mb-1">Subtask Order <span class="text-red-500">*</span></label>
                    <input type="number" name="task_order" id="modal_subtask_order" min="1" value="1" required
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="task_order"></p>
                </div>
                <div>
                    <label for="modal_subtask_weight" class="block text-xs font-medium text-gray-700 mb-1">Weight (%)</label>
                    <input type="number" name="weight" id="modal_subtask_weight" min="0" max="100" value="0"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="weight"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_subtask_phase_id" class="block text-xs font-medium text-gray-700 mb-1">Parent Task</label>
                    <select name="phase_id" id="modal_subtask_phase_id"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— No Parent Task (Standalone) —</option>
                        @foreach($phases as $parentPhase)
                            <option value="{{ $parentPhase->id }}">{{ $parentPhase->phase_name }}</option>
                        @endforeach
                    </select>
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_id"></p>
                </div>
                <div>
                    <label for="modal_subtask_status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="modal_subtask_status"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="modal_subtask_assigned_to" class="block text-xs font-medium text-gray-700 mb-1">Assigned To</label>
                <select name="assigned_to" id="modal_subtask_assigned_to"
                        class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Unassigned —</option>
                    @foreach($staffList ?? [] as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_subtask_predecessor" class="block text-xs font-medium text-gray-700 mb-1">Dependency</label>
                    <select name="predecessor_task_id" id="modal_subtask_predecessor"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— No Dependency —</option>
                        @foreach($allSubtasks as $predecessor)
                            <option value="{{ $predecessor->id }}" data-start-date-plan="{{ $predecessor->start_date_plan?->format('Y-m-d') }}" data-end-date-plan="{{ $predecessor->end_date_plan?->format('Y-m-d') }}">{{ $predecessor->task_name }}</option>
                        @endforeach
                    </select>
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="predecessor_task_id"></p>
                </div>
                <div>
                    <label for="modal_subtask_dependency_type" class="block text-xs font-medium text-gray-700 mb-1">Dependency Type</label>
                    <select name="dependency_type" id="modal_subtask_dependency_type"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="end_to_start">End-to-Start</option>
                        <option value="start_to_start">Start-to-Start</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_subtask_start_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan Start</label>
                    <input type="date" name="start_date_plan" id="modal_subtask_start_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="start_date_plan"></p>
                </div>
                <div>
                    <label for="modal_subtask_end_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan End</label>
                    <input type="date" name="end_date_plan" id="modal_subtask_end_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="end_date_plan"></p>
                </div>
            </div>
            <div id="subtask-create-general-error" class="hidden p-2 bg-red-50 text-xs text-red-700 rounded"></div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeSubtaskCreateModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">Create Subtask</button>
            </div>
        </form>
    </div>
</div>

{{-- Task Edit Modal --}}
<div id="task-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-[420px] max-w-full mx-4 overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Edit Task</h4>
            <button type="button" onclick="closeTaskEditModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="task-edit-form" method="POST" class="p-4 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
            <div>
                <label for="modal_edit_task_name" class="block text-xs font-medium text-gray-700 mb-1">Task Name <span class="text-red-500">*</span></label>
                <input type="text" name="phase_name" id="modal_edit_task_name" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_name"></p>
            </div>
            <div>
                <label for="modal_edit_task_order" class="block text-xs font-medium text-gray-700 mb-1">Task Order <span class="text-red-500">*</span></label>
                <input type="number" name="phase_order" id="modal_edit_task_order" min="1" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_order"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_task_start_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan Start</label>
                    <input type="date" name="start_date_plan" id="modal_edit_task_start_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="start_date_plan"></p>
                </div>
                <div>
                    <label for="modal_edit_task_end_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan End</label>
                    <input type="date" name="end_date_plan" id="modal_edit_task_end_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="end_date_plan"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_task_start_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual Start</label>
                    <input type="date" name="start_date_actual" id="modal_edit_task_start_date_actual"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_edit_task_end_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual End</label>
                    <input type="date" name="end_date_actual" id="modal_edit_task_end_date_actual"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_task_start_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise Start</label>
                    <input type="date" name="start_date_revise" id="modal_edit_task_start_date_revise"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_edit_task_end_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise End</label>
                    <input type="date" name="end_date_revise" id="modal_edit_task_end_date_revise"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div id="task-edit-general-error" class="hidden p-2 bg-red-50 text-xs text-red-700 rounded"></div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeTaskEditModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">Update Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Subtask Edit Modal --}}
<div id="subtask-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-[480px] max-w-full mx-4 overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Edit Subtask</h4>
            <button type="button" onclick="closeSubtaskEditModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="subtask-edit-form" method="POST" class="p-4 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="schedule">
            <input type="hidden" name="view" value="">
            <div>
                <label for="modal_edit_subtask_name" class="block text-xs font-medium text-gray-700 mb-1">Subtask Name <span class="text-red-500">*</span></label>
                <input type="text" name="task_name" id="modal_edit_subtask_name" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="task_name"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_order" class="block text-xs font-medium text-gray-700 mb-1">Subtask Order <span class="text-red-500">*</span></label>
                    <input type="number" name="task_order" id="modal_edit_subtask_order" min="1" required
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="task_order"></p>
                </div>
                <div>
                    <label for="modal_edit_subtask_weight" class="block text-xs font-medium text-gray-700 mb-1">Weight (%)</label>
                    <input type="number" name="weight" id="modal_edit_subtask_weight" min="0" max="100"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="weight"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_phase_id" class="block text-xs font-medium text-gray-700 mb-1">Parent Task</label>
                    <select name="phase_id" id="modal_edit_subtask_phase_id"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— No Parent Task (Standalone) —</option>
                        @foreach($phases as $parentPhase)
                            <option value="{{ $parentPhase->id }}">{{ $parentPhase->phase_name }}</option>
                        @endforeach
                    </select>
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="phase_id"></p>
                </div>
                <div>
                    <label for="modal_edit_subtask_status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="modal_edit_subtask_status"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="modal_edit_subtask_assigned_to" class="block text-xs font-medium text-gray-700 mb-1">Assigned To</label>
                <select name="assigned_to" id="modal_edit_subtask_assigned_to"
                        class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Unassigned —</option>
                    @foreach($staffList ?? [] as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_predecessor" class="block text-xs font-medium text-gray-700 mb-1">Dependency</label>
                    <select name="predecessor_task_id" id="modal_edit_subtask_predecessor"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— No Dependency —</option>
                        @foreach($allSubtasks as $predecessor)
                            <option value="{{ $predecessor->id }}" data-start-date-plan="{{ $predecessor->start_date_plan?->format('Y-m-d') }}" data-end-date-plan="{{ $predecessor->end_date_plan?->format('Y-m-d') }}">{{ $predecessor->task_name }}</option>
                        @endforeach
                    </select>
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="predecessor_task_id"></p>
                </div>
                <div>
                    <label for="modal_edit_subtask_dependency_type" class="block text-xs font-medium text-gray-700 mb-1">Dependency Type</label>
                    <select name="dependency_type" id="modal_edit_subtask_dependency_type"
                            class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="end_to_start">End-to-Start</option>
                        <option value="start_to_start">Start-to-Start</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_start_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan Start</label>
                    <input type="date" name="start_date_plan" id="modal_edit_subtask_start_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="start_date_plan"></p>
                </div>
                <div>
                    <label for="modal_edit_subtask_end_date_plan" class="block text-xs font-medium text-gray-700 mb-1">Plan End</label>
                    <input type="date" name="end_date_plan" id="modal_edit_subtask_end_date_plan"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="modal-error text-xs text-red-600 mt-1 hidden" data-field="end_date_plan"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_start_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual Start</label>
                    <input type="date" name="start_date_actual" id="modal_edit_subtask_start_date_actual"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_edit_subtask_end_date_actual" class="block text-xs font-medium text-gray-700 mb-1">Actual End</label>
                    <input type="date" name="end_date_actual" id="modal_edit_subtask_end_date_actual"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="modal_edit_subtask_start_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise Start</label>
                    <input type="date" name="start_date_revise" id="modal_edit_subtask_start_date_revise"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="modal_edit_subtask_end_date_revise" class="block text-xs font-medium text-gray-700 mb-1">Revise End</label>
                    <input type="date" name="end_date_revise" id="modal_edit_subtask_end_date_revise"
                           class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div id="subtask-edit-general-error" class="hidden p-2 bg-red-50 text-xs text-red-700 rounded"></div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeSubtaskEditModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">Update Subtask</button>
            </div>
        </form>
    </div>
</div>

{{-- Weight Edit Modal --}}
<div id="weight-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-80 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Edit Weight</h4>
            <button type="button" onclick="closeWeightEditModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="weight-edit-form" method="POST" class="p-4 space-y-4">
            @csrf
            <div>
                <p id="modal_weight_task_name" class="text-sm text-gray-900 font-medium truncate"></p>
                <p class="text-xs text-gray-500">Weight (%)</p>
            </div>
            <div>
                <label for="modal_weight_value" class="block text-xs font-medium text-gray-700 mb-1">Weight (0-100)</label>
                <input type="number" id="modal_weight_value" name="weight" min="0" max="100" value="0" required
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p id="modal_weight_error" class="text-xs text-red-600 mt-1 hidden"></p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeWeightEditModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Date Edit Modal --}}
<div id="date-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="bg-white rounded-2xl shadow-xl w-96 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Edit Date</h4>
            <button type="button" onclick="closeDateEditModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="date-edit-form" method="POST" class="p-4 space-y-4">
            @csrf
            <input type="hidden" id="modal_date_field" name="field" value="">
            <div>
                <p id="modal_date_task_name" class="text-sm text-gray-900 font-medium truncate"></p>
                <p id="modal_date_field_label" class="text-xs text-gray-500"></p>
            </div>
            <div>
                <label for="modal_date_value" class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                <input type="date" id="modal_date_value" name="value"
                       class="w-full text-sm border border-gray-300 rounded-md shadow-sm px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                <p id="modal_date_error" class="text-xs text-red-600 mt-1 hidden"></p>
                <p id="modal_date_constraint" class="text-xs text-orange-600 mt-1 hidden"></p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="clearDateValue()" class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100">Clear</button>
                <button type="button" onclick="closeDateEditModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">Save</button>
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

    function openTaskCreateModal() {
        document.getElementById('task-create-form').reset();
        document.getElementById('modal_phase_order').value = {{ $phases->count() + 1 }};
        document.getElementById('task-create-general-error').classList.add('hidden');
        document.querySelectorAll('#task-create-form .modal-error').forEach(function(el) { el.classList.add('hidden'); el.textContent = ''; });
        document.getElementById('task-create-modal').classList.remove('hidden');
    }

    function closeTaskCreateModal() {
        document.getElementById('task-create-modal').classList.add('hidden');
    }

    function openSubtaskCreateModal() {
        document.getElementById('subtask-create-form').reset();
        document.getElementById('modal_subtask_order').value = {{ $allSubtasks->count() + 1 }};
        document.getElementById('subtask-create-general-error').classList.add('hidden');
        document.querySelectorAll('#subtask-create-form .modal-error').forEach(function(el) { el.classList.add('hidden'); el.textContent = ''; });
        document.getElementById('subtask-create-modal').classList.remove('hidden');
    }

    function closeSubtaskCreateModal() {
        document.getElementById('subtask-create-modal').classList.add('hidden');
    }

    function openTaskEditModal(phaseData) {
        var form = document.getElementById('task-edit-form');
        form.action = phaseData.updateUrl;
        document.getElementById('modal_edit_task_name').value = phaseData.phase_name || '';
        document.getElementById('modal_edit_task_order').value = phaseData.phase_order || 1;
        document.getElementById('modal_edit_task_start_date_plan').value = formatDateForInput(phaseData.start_date_plan);
        document.getElementById('modal_edit_task_end_date_plan').value = formatDateForInput(phaseData.end_date_plan);
        document.getElementById('modal_edit_task_start_date_actual').value = formatDateForInput(phaseData.start_date_actual);
        document.getElementById('modal_edit_task_end_date_actual').value = formatDateForInput(phaseData.end_date_actual);
        document.getElementById('modal_edit_task_start_date_revise').value = formatDateForInput(phaseData.start_date_revise);
        document.getElementById('modal_edit_task_end_date_revise').value = formatDateForInput(phaseData.end_date_revise);
        document.getElementById('task-edit-general-error').classList.add('hidden');
        document.querySelectorAll('#task-edit-form .modal-error').forEach(function(el) { el.classList.add('hidden'); el.textContent = ''; });
        document.getElementById('task-edit-modal').classList.remove('hidden');
    }

    function closeTaskEditModal() {
        document.getElementById('task-edit-modal').classList.add('hidden');
    }

    function openSubtaskEditModal(taskData) {
        var form = document.getElementById('subtask-edit-form');
        form.action = taskData.updateUrl;
        document.getElementById('modal_edit_subtask_name').value = taskData.task_name || '';
        document.getElementById('modal_edit_subtask_order').value = taskData.task_order || 1;
        document.getElementById('modal_edit_subtask_weight').value = taskData.weight || 0;
        document.getElementById('modal_edit_subtask_phase_id').value = taskData.phase_id || '';
        document.getElementById('modal_edit_subtask_status').value = taskData.status || 'not_started';
        document.getElementById('modal_edit_subtask_assigned_to').value = taskData.assigned_to || '';
        document.getElementById('modal_edit_subtask_predecessor').value = taskData.predecessor_task_id || '';
        document.getElementById('modal_edit_subtask_dependency_type').value = taskData.dependency_type || 'end_to_start';
        document.getElementById('modal_edit_subtask_start_date_plan').value = formatDateForInput(taskData.start_date_plan);
        document.getElementById('modal_edit_subtask_end_date_plan').value = formatDateForInput(taskData.end_date_plan);
        document.getElementById('modal_edit_subtask_start_date_actual').value = formatDateForInput(taskData.start_date_actual);
        document.getElementById('modal_edit_subtask_end_date_actual').value = formatDateForInput(taskData.end_date_actual);
        document.getElementById('modal_edit_subtask_start_date_revise').value = formatDateForInput(taskData.start_date_revise);
        document.getElementById('modal_edit_subtask_end_date_revise').value = formatDateForInput(taskData.end_date_revise);
        document.getElementById('subtask-edit-general-error').classList.add('hidden');
        document.querySelectorAll('#subtask-edit-form .modal-error').forEach(function(el) { el.classList.add('hidden'); el.textContent = ''; });
        document.getElementById('subtask-edit-modal').classList.remove('hidden');
    }

    function closeSubtaskEditModal() {
        document.getElementById('subtask-edit-modal').classList.add('hidden');
    }

    function formatDateForInput(date) {
        if (!date) return '';
        var d = new Date(date + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function addOneDay(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + 1);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function applyDependencyToSubtask() {
        var predecessorSelect = document.getElementById('modal_subtask_predecessor');
        var dependencyTypeSelect = document.getElementById('modal_subtask_dependency_type');
        var startInput = document.getElementById('modal_subtask_start_date_plan');

        var selected = predecessorSelect.options[predecessorSelect.selectedIndex];
        if (!selected || !selected.value) {
            startInput.min = '';
            return;
        }

        var dependencyType = dependencyTypeSelect.value;
        var startDatePlan = selected.getAttribute('data-start-date-plan');
        var endDatePlan = selected.getAttribute('data-end-date-plan');

        if (dependencyType === 'start_to_start') {
            startInput.value = startDatePlan || '';
            startInput.min = startDatePlan || '';
        } else {
            var minDate = addOneDay(endDatePlan);
            startInput.min = minDate || '';
            if (startInput.value && startInput.value < minDate) {
                startInput.value = minDate;
            }
        }
    }

    document.getElementById('modal_subtask_predecessor').addEventListener('change', applyDependencyToSubtask);
    document.getElementById('modal_subtask_dependency_type').addEventListener('change', applyDependencyToSubtask);

    function applyDependencyToSubtaskEdit() {
        var predecessorSelect = document.getElementById('modal_edit_subtask_predecessor');
        var dependencyTypeSelect = document.getElementById('modal_edit_subtask_dependency_type');
        var startInput = document.getElementById('modal_edit_subtask_start_date_plan');

        var selected = predecessorSelect.options[predecessorSelect.selectedIndex];
        if (!selected || !selected.value) {
            startInput.min = '';
            return;
        }

        var dependencyType = dependencyTypeSelect.value;
        var startDatePlan = selected.getAttribute('data-start-date-plan');
        var endDatePlan = selected.getAttribute('data-end-date-plan');

        if (dependencyType === 'start_to_start') {
            startInput.value = startDatePlan || '';
            startInput.min = startDatePlan || '';
        } else {
            var minDate = addOneDay(endDatePlan);
            startInput.min = minDate || '';
            if (startInput.value && startInput.value < minDate) {
                startInput.value = minDate;
            }
        }
    }

    document.getElementById('modal_edit_subtask_predecessor').addEventListener('change', applyDependencyToSubtaskEdit);
    document.getElementById('modal_edit_subtask_dependency_type').addEventListener('change', applyDependencyToSubtaskEdit);

    function submitModalForm(formId, modalId, onSuccess) {
        var form = document.getElementById(formId);
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function(value, key) { data[key] = value; });

        // Include _method if present in the form (for PUT/PATCH requests)
        var methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) {
            data._method = methodInput.value;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            if (response.success) {
                document.getElementById(modalId).classList.add('hidden');
                if (onSuccess) onSuccess(response);
                window.location.reload();
            } else {
                showModalErrors(form, response.errors || {}, response.message || 'Failed to save.');
            }
        })
        .catch(function(error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            showModalErrors(form, {}, 'An error occurred while saving. Please try again.');
        });

        return false;
    }

    function showModalErrors(form, errors, generalMessage) {
        form.querySelectorAll('.modal-error').forEach(function(el) {
            el.classList.add('hidden');
            el.textContent = '';
        });

        var generalError = form.querySelector('[id$="-general-error"]');
        if (generalError) {
            if (generalMessage) {
                generalError.textContent = generalMessage;
                generalError.classList.remove('hidden');
            } else {
                generalError.classList.add('hidden');
            }
        }

        for (var field in errors) {
            var errorEl = form.querySelector('.modal-error[data-field="' + field + '"]');
            if (errorEl) {
                var message = Array.isArray(errors[field]) ? errors[field].join(' ') : errors[field];
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            }
        }
    }

    document.getElementById('task-create-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitModalForm('task-create-form', 'task-create-modal');
    });

    document.getElementById('subtask-create-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitModalForm('subtask-create-form', 'subtask-create-modal');
    });

    document.getElementById('task-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitModalForm('task-edit-form', 'task-edit-modal');
    });

    document.getElementById('subtask-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitModalForm('subtask-edit-form', 'subtask-edit-modal');
    });

    document.getElementById('task-create-modal').addEventListener('click', function(e) {
        if (e.target === this) closeTaskCreateModal();
    });

    document.getElementById('subtask-create-modal').addEventListener('click', function(e) {
        if (e.target === this) closeSubtaskCreateModal();
    });

    document.getElementById('task-edit-modal').addEventListener('click', function(e) {
        if (e.target === this) closeTaskEditModal();
    });

    document.getElementById('subtask-edit-modal').addEventListener('click', function(e) {
        if (e.target === this) closeSubtaskEditModal();
    });

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
    var gcmCurrentBtn = null;
    var gcmCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    gcmEdit.addEventListener('mouseover', function() { this.style.backgroundColor = '#f3f4f6'; });
    gcmEdit.addEventListener('mouseout', function() { this.style.backgroundColor = ''; });
    gcmDelete.addEventListener('mouseover', function() { this.style.backgroundColor = '#f3f4f6'; });
    gcmDelete.addEventListener('mouseout', function() { this.style.backgroundColor = ''; });

    gcmEdit.addEventListener('click', function(e) {
        e.preventDefault();
        ganttDropdown.classList.add('hidden');
        if (!gcmCurrentBtn) return;

        var editType = gcmCurrentBtn.dataset.editType;
        if (editType === 'phase') {
            openTaskEditModal({
                updateUrl: gcmCurrentBtn.dataset.updateUrl,
                phase_name: gcmCurrentBtn.dataset.phaseName,
                phase_order: gcmCurrentBtn.dataset.phaseOrder,
                start_date_plan: gcmCurrentBtn.dataset.startDatePlan,
                end_date_plan: gcmCurrentBtn.dataset.endDatePlan,
                start_date_actual: gcmCurrentBtn.dataset.startDateActual,
                end_date_actual: gcmCurrentBtn.dataset.endDateActual,
                start_date_revise: gcmCurrentBtn.dataset.startDateRevise,
                end_date_revise: gcmCurrentBtn.dataset.endDateRevise
            });
        } else if (editType === 'task') {
            openSubtaskEditModal({
                updateUrl: gcmCurrentBtn.dataset.updateUrl,
                task_name: gcmCurrentBtn.dataset.taskName,
                task_order: gcmCurrentBtn.dataset.taskOrder,
                weight: gcmCurrentBtn.dataset.weight,
                phase_id: gcmCurrentBtn.dataset.phaseId,
                status: gcmCurrentBtn.dataset.status,
                assigned_to: gcmCurrentBtn.dataset.assignedTo,
                predecessor_task_id: gcmCurrentBtn.dataset.predecessorTaskId,
                dependency_type: gcmCurrentBtn.dataset.dependencyType,
                start_date_plan: gcmCurrentBtn.dataset.startDatePlan,
                end_date_plan: gcmCurrentBtn.dataset.endDatePlan,
                start_date_actual: gcmCurrentBtn.dataset.startDateActual,
                end_date_actual: gcmCurrentBtn.dataset.endDateActual,
                start_date_revise: gcmCurrentBtn.dataset.startDateRevise,
                end_date_revise: gcmCurrentBtn.dataset.endDateRevise
            });
        }
    });

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

            gcmCurrentBtn = this;
            gcmDeleteAction = this.dataset.deleteAction;
            gcmDeleteConfirm = this.dataset.deleteConfirm;

            var rect = this.getBoundingClientRect();
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
            var offset = parseFloat(line.dataset.dayOffset);
            if (isNaN(offset)) return;
            line.style.left = (offset * pixelsPerDay) + 'px';

            // Reset defaults
            line.style.width = '1px';
            line.style.backgroundColor = '#e5e7eb';
            line.style.zIndex = '';
            line.style.display = '';

            var date = new Date(line.dataset.dayDate + 'T00:00:00');
            if (isNaN(date.getTime())) {
                line.style.display = 'none';
                return;
            }

            var isMonday = date.getDay() === 1;
            var isMonthStart = date.getDate() === 1;
            var isYearStart = date.getMonth() === 0 && date.getDate() === 1;
            var isWeekStartFromTimeline = offset % 7 === 0;

            if (zoomLevel === 'day') {
                if (isMonday) {
                    line.style.backgroundColor = '#d1d5db';
                } else {
                    line.style.backgroundColor = '#f3f4f6';
                }
            } else if (zoomLevel === 'week') {
                if (isWeekStartFromTimeline) {
                    line.style.backgroundColor = '#d1d5db';
                } else {
                    line.style.display = 'none';
                }
            } else if (zoomLevel === 'month') {
                if (isMonthStart) {
                    line.style.backgroundColor = '#d1d5db';
                } else {
                    line.style.display = 'none';
                }
            } else if (zoomLevel === 'year') {
                if (isYearStart) {
                    line.style.backgroundColor = '#d1d5db';
                } else {
                    line.style.display = 'none';
                }
            }
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
        try { localStorage.setItem('ganttZoomLevel', zoomLevel); } catch (e) {}
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
        var savedZoom = 'day';
        try { savedZoom = localStorage.getItem('ganttZoomLevel') || 'day'; } catch (e) {}
        ganttApplyZoom(savedZoom);
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

        // Flowchart-style stepped connector (straight vertical/horizontal lines)
        var margin = 6;
        var elbowX, elbowY;

        if (typeParts.fromSide === 'end') {
            // End-to-Start: from right side of predecessor
            elbowX = x1 + margin;
            if (x2 < elbowX) {
                // Target is to the left, need to go around
                elbowX = x1 + margin + 10;
            }
        } else {
            // Start-to-Start: from left side of predecessor
            elbowX = x1 - margin;
            if (x2 > elbowX) {
                elbowX = x1 - margin - 10;
            }
        }

        // Create stepped path: from source → horizontal → vertical → horizontal → target
        // Start at source point
        // Move slightly outward from bar
        var x1Out = typeParts.fromSide === 'end' ? x1 + margin : x1 - margin;
        // Enter target slightly before
        var x2In = typeParts.toSide === 'start' ? x2 - margin : x2 + margin;

        // Use the midpoint of the two rows as the horizontal level
        var midY = y1 + (y2 - y1) / 2;

        // Build the stepped path
        var d = 'M ' + x1 + ' ' + y1
            + ' L ' + x1Out + ' ' + y1
            + ' L ' + x1Out + ' ' + midY
            + ' L ' + x2In + ' ' + midY
            + ' L ' + x2In + ' ' + y2
            + ' L ' + x2 + ' ' + y2;

        var label = typeParts.fromSide === 'start'
            ? 'Start-to-Start (' + lane + ')'
            : 'End-to-Start (' + lane + ')';

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        var strokeColor = {
            plan: '#4b5563',
            revise: '#c2410c',
            actual: '#059669'
        }[lane] || '#4b5563';
        path.setAttribute('stroke', strokeColor);
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', 'url(#gantt-arrowhead-' + lane + ')');
        path.setAttribute('stroke-linecap', 'butt');
        path.setAttribute('stroke-linejoin', 'round');
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
    var ganttUpdateDependencyUrl = '{{ route("project.projects.tasks.dependency.update", ["project" => $project->id, "task" => ":taskId"]) }}';
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
                alert('Subtasks can only be reordered within the same task.');
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
        var ganttShiftedBars = [];
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

            // Live preview: shift dependent task bars
            if (ganttResizing && ganttResizing.bar === bar) {
                ganttShiftSuccessors(bar.dataset.taskId, bar.dataset.barType, ganttResizing.startOffset, ganttResizing.duration, newStartOffset, newDuration, pixelsPerDay);
            }
        }

        function ganttShiftSuccessors(predecessorTaskId, barType, oldPredecessorStart, oldPredecessorDuration, newPredecessorStart, newPredecessorDuration, pixelsPerDay, visited) {
            if (!predecessorTaskId || !barType) return;

            visited = visited || new Set();
            if (visited.has(predecessorTaskId)) return;
            visited.add(predecessorTaskId);

            var oldPredecessorEnd = oldPredecessorStart + oldPredecessorDuration - 1;
            var newPredecessorEnd = newPredecessorStart + newPredecessorDuration - 1;
            var startDelta = newPredecessorStart - oldPredecessorStart;
            var endDelta = newPredecessorEnd - oldPredecessorEnd;

            document.querySelectorAll('.task-row[data-predecessor-id="' + predecessorTaskId + '"]').forEach(function(row) {
                var dependencyType = row.dataset.dependencyType || 'end_to_start';
                var successorId = row.dataset.taskId;
                if (!successorId) return;

                // Do not auto-shift if the successor already has a manual pending drag for this lane
                var hasPending = ganttPending.some(function(p) {
                    return p.taskId === successorId && p.barType === barType;
                });
                if (hasPending) return;

                var successorRow = document.querySelector('.task-row[data-task-id="' + successorId + '"]');
                if (!successorRow) return;
                var successorBar = successorRow.querySelector('.gantt-timeline-area .gantt-bar[data-bar-type="' + barType + '"]');
                if (!successorBar) return;

                // Store original only the first time this bar is shifted
                var existing = ganttShiftedBars.find(function(s) { return s.bar === successorBar; });
                if (!existing) {
                    ganttShiftedBars.push({
                        bar: successorBar,
                        originalLeft: successorBar.style.left,
                        originalWidth: successorBar.style.width,
                        originalStartOffset: successorBar.dataset.startOffset,
                        originalDuration: successorBar.dataset.duration
                    });
                }

                // Calculate from the original start/duration so repeated mousemove events
                // do not accumulate and overshift the successor.
                var originalSuccessorStart = existing ? parseFloat(existing.originalStartOffset) || 0 : parseFloat(successorBar.dataset.startOffset) || 0;
                var successorDuration = existing ? parseFloat(existing.originalDuration) || 1 : parseFloat(successorBar.dataset.duration) || 1;
                var newSuccessorStart = originalSuccessorStart;

                if (dependencyType === 'start_to_start') {
                    newSuccessorStart = originalSuccessorStart + startDelta;
                } else {
                    // end_to_start: shift based on predecessor end change
                    newSuccessorStart = originalSuccessorStart + endDelta;
                }

                if (newSuccessorStart < 0) newSuccessorStart = 0;

                successorBar.style.left = (newSuccessorStart * pixelsPerDay) + 'px';
                successorBar.style.width = (successorDuration * pixelsPerDay) + 'px';
                successorBar.dataset.startOffset = newSuccessorStart;

                // Cascade one more level for direct successors only
                ganttShiftSuccessors(successorId, barType, originalSuccessorStart, successorDuration, newSuccessorStart, successorDuration, pixelsPerDay, visited);
            });
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
            ganttShiftedBars.forEach(function(s) {
                var bar = s.bar;
                if (!bar) return;
                bar.style.left = s.originalLeft;
                bar.style.width = s.originalWidth;
                bar.dataset.startOffset = s.originalStartOffset;
                bar.dataset.duration = s.originalDuration;
            });
            ganttPending = [];
            ganttShiftedBars = [];
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

                // Revert any live-shifted dependent bars; the backend will cascade on save and the page will reload
                ganttShiftedBars.forEach(function(s) {
                    var bar = s.bar;
                    if (!bar) return;
                    bar.style.left = s.originalLeft;
                    bar.style.width = s.originalWidth;
                    bar.dataset.startOffset = s.originalStartOffset;
                    bar.dataset.duration = s.originalDuration;
                });
                ganttShiftedBars = [];

                var queue = ganttPending.slice();
                var results = [];
                var failures = [];

                function sendNext(index) {
                    if (index >= queue.length) {
                        if (failures.length > 0) {
                            var messages = failures.map(function(f) {
                                return f.name + ' (' + f.barType + '): ' + f.message;
                            });
                            alert('Some changes could not be saved:\n\n' + messages.join('\n'));
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
                    .then(function(r) {
                        if (!r.ok && r.status === 422) {
                            return r.json().then(function(data) { throw data; });
                        }
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            results.push(data);
                            // Remove this pending item from the list after success
                            ganttPending = ganttPending.filter(function(item) { return ganttPendingKey(item) !== ganttPendingKey(p); });
                            ganttUpdatePendingActionsUi();
                            sendNext(index + 1);
                        } else {
                            throw data;
                        }
                    })
                    .catch(function(data) {
                        // Revert the failed bar and remove it from the queue
                        if (p.bar) {
                            p.bar.style.left = p.originalLeft;
                            p.bar.style.width = p.originalWidth;
                            p.bar.dataset.startOffset = p.originalStartOffset;
                            p.bar.dataset.duration = p.originalDuration;
                        }
                        ganttPending = ganttPending.filter(function(item) { return ganttPendingKey(item) !== ganttPendingKey(p); });
                        ganttUpdatePendingActionsUi();

                        var taskName = '';
                        if (p.bar && p.bar.closest) {
                            var row = p.bar.closest('.task-row');
                            if (row) taskName = row.dataset.taskName || '';
                        }
                        var errorMessage = data && data.message ? data.message : 'Failed to save.';
                        failures.push({ name: taskName, barType: p.barType, message: errorMessage });

                        // Continue with the rest of the queue
                        sendNext(index + 1);
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

    // Date edit modal functions
    var activeDateCell = null;
    var fieldLabels = {
        'start_date_plan': 'Plan Start',
        'end_date_plan': 'Plan End',
        'start_date_revise': 'Revise Start',
        'end_date_revise': 'Revise End',
        'start_date_actual': 'Actual Start',
        'end_date_actual': 'Actual End',
    };

    function openDateEditModal(cell) {
        activeDateCell = cell;
        var field = cell.dataset.field;
        var taskName = cell.dataset.taskName;
        var dateValue = cell.dataset.date || '';
        var pairDate = cell.dataset.pairDate || '';

        var form = document.getElementById('date-edit-form');
        var dateInput = document.getElementById('modal_date_value');

        form.action = cell.dataset.updateUrl;
        document.getElementById('modal_date_field').value = field;
        document.getElementById('modal_date_task_name').textContent = taskName;
        document.getElementById('modal_date_field_label').textContent = 'Edit ' + (fieldLabels[field] || field);
        dateInput.value = dateValue;
        dateInput.min = '';
        dateInput.max = '';

        document.getElementById('modal_date_error').classList.add('hidden');
        document.getElementById('modal_date_constraint').classList.add('hidden');

        // Basic constraint: end date must be >= start date of the same lane
        if (field.startsWith('end_date_') && pairDate) {
            dateInput.min = pairDate;
            document.getElementById('modal_date_constraint').textContent = 'Must be on or after ' + (fieldLabels[field.replace('end', 'start')] || 'start date');
            document.getElementById('modal_date_constraint').classList.remove('hidden');
        }

        // Basic constraint: start date must be <= end date of the same lane
        if (field.startsWith('start_date_') && pairDate) {
            dateInput.max = pairDate;
            document.getElementById('modal_date_constraint').textContent = 'Must be on or before ' + (fieldLabels[field.replace('start', 'end')] || 'end date');
            document.getElementById('modal_date_constraint').classList.remove('hidden');
        }

        document.getElementById('date-edit-modal').classList.remove('hidden');
    }

    function closeDateEditModal() {
        document.getElementById('date-edit-modal').classList.add('hidden');
        activeDateCell = null;
    }

    function clearDateValue() {
        document.getElementById('modal_date_value').value = '';
    }

    document.querySelectorAll('.gantt-date-cell').forEach(function(cell) {
        cell.addEventListener('click', function() {
            openDateEditModal(cell);
        });
    });

    document.getElementById('date-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = document.getElementById('date-edit-form');
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;

        var field = document.getElementById('modal_date_field').value;
        var value = document.getElementById('modal_date_value').value;
        var data = {};
        // Send empty string to clear the date
        data[field] = value || '';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        document.getElementById('modal_date_error').classList.add('hidden');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            if (response.success) {
                closeDateEditModal();
                window.location.reload();
            } else {
                var message = response.message || 'Failed to save date.';
                var errorEl = document.getElementById('modal_date_error');
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            }
        })
        .catch(function(error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            var errorEl = document.getElementById('modal_date_error');
            errorEl.textContent = 'An error occurred while saving. Please try again.';
            errorEl.classList.remove('hidden');
        });
    });

    document.getElementById('date-edit-modal').addEventListener('click', function(e) {
        if (e.target === this) closeDateEditModal();
    });

    // Weight edit
    var activeWeightCell = null;

    function openWeightEditModal(cell) {
        activeWeightCell = cell;

        var form = document.getElementById('weight-edit-form');
        form.action = cell.dataset.updateUrl;
        document.getElementById('modal_weight_task_name').textContent = cell.dataset.taskName;
        document.getElementById('modal_weight_value').value = cell.dataset.weight;
        document.getElementById('modal_weight_error').classList.add('hidden');

        document.getElementById('weight-edit-modal').classList.remove('hidden');
    }

    function closeWeightEditModal() {
        document.getElementById('weight-edit-modal').classList.add('hidden');
        activeWeightCell = null;
    }

    document.querySelectorAll('.gantt-weight-cell').forEach(function(cell) {
        cell.addEventListener('click', function() {
            openWeightEditModal(cell);
        });
    });

    document.getElementById('weight-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = document.getElementById('weight-edit-form');
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;

        var weight = document.getElementById('modal_weight_value').value;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        document.getElementById('modal_weight_error').classList.add('hidden');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ weight: weight })
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            if (response.success) {
                closeWeightEditModal();
                window.location.reload();
            } else {
                var errorEl = document.getElementById('modal_weight_error');
                errorEl.textContent = response.message || 'Failed to save weight.';
                errorEl.classList.remove('hidden');
            }
        })
        .catch(function(error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            var errorEl = document.getElementById('modal_weight_error');
            errorEl.textContent = 'An error occurred while saving. Please try again.';
            errorEl.classList.remove('hidden');
        });
    });

    document.getElementById('weight-edit-modal').addEventListener('click', function(e) {
        if (e.target === this) closeWeightEditModal();
    });

    // Start Actual button
    document.querySelectorAll('.gantt-start-actual-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();

            var predecessorId = btn.dataset.predecessorId;
            var dependencyType = btn.dataset.dependencyType || 'end_to_start';
            var predecessorActualEnd = btn.dataset.predecessorActualEnd;
            var predecessorActualStart = btn.dataset.predecessorActualStart || '';

            if (predecessorId) {
                // Check if predecessor is complete based on dependency type
                var predecessorComplete = false;
                if (dependencyType === 'start_to_start') {
                    predecessorComplete = !!predecessorActualStart;
                } else {
                    // end_to_start (default)
                    predecessorComplete = !!predecessorActualEnd;
                }

                if (!predecessorComplete) {
                    var depTypeText = dependencyType === 'start_to_start' ? 'started' : 'completed';
                    alert('This subtask cannot start because its predecessor has not been ' + depTypeText + ' yet.');
                    return;
                }
            }

            if (!confirm('Set Actual Start to today for "' + btn.dataset.taskName + '"?')) {
                return;
            }

            var today = new Date();
            var y = today.getFullYear();
            var m = String(today.getMonth() + 1).padStart(2, '0');
            var day = String(today.getDate()).padStart(2, '0');
            var todayStr = y + '-' + m + '-' + day;

            var data = { start_date_actual: todayStr };

            fetch(btn.dataset.updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.message || 'Failed to set Actual Start.');
                }
            })
            .catch(function(error) {
                alert('An error occurred. Please try again.');
            });
        });
    });

</script>

{{-- Dependency Delete Confirmation Modal --}}
<div id="gantt-dependency-delete-modal" class="hidden fixed inset-0 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5); pointer-events: auto; z-index: 9999;">
    <div class="bg-white rounded-2xl shadow-xl w-80 max-w-full mx-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <h4 class="text-sm font-semibold text-gray-800">Delete Dependency?</h4>
        </div>
        <div class="px-4 py-3">
            <p class="text-xs text-gray-600">Remove this dependency link?</p>
        </div>
        <div class="px-4 py-3 bg-gray-50 flex justify-end gap-2">
            <button id="gantt-dep-delete-cancel" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 hover:shadow-sm transition">Cancel</button>
            <button id="gantt-dep-delete-confirm" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition">Delete</button>
        </div>
    </div>
</div>

{{-- Progress Update Modal --}}
<div id="gantt-progress-modal" class="hidden fixed inset-0 flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5); pointer-events: auto; z-index: 9999;">
    <div class="bg-white rounded-2xl shadow-xl w-80 max-w-full mx-4 overflow-hidden">
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
                <button id="gantt-progress-cancel" type="button" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 hover:shadow-sm transition">Cancel</button>
                <button id="gantt-progress-save" type="button" class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Recent Gantt Changes Box --}}
<div class="bg-white border border-gray-200 rounded-2xl shadow-sm mt-4 overflow-hidden">
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
