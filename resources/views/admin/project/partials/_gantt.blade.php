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
                <button type="button" class="gantt-zoom-btn px-2 py-1 text-xs font-medium rounded border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition" data-zoom="day">Day</button>
                <button type="button" class="gantt-zoom-btn px-2 py-1 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="week">Week</button>
                <button type="button" class="gantt-zoom-btn px-2 py-1 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="month">Month</button>
                <button type="button" class="gantt-zoom-btn px-2 py-1 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition" data-zoom="year">Year</button>
            </div>
            <button type="button" id="gantt-fullscreen-btn" class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition mr-2" title="Fullscreen">
                <svg id="gantt-icon-expand" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                <svg id="gantt-icon-contract" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4M4 10V6m0 0h4M4 6l5 5m11-5l-5 5m5-5V6m0 0h-4"/>
                </svg>
            </button>
            <a href="{{ route('admin.project.projects.phases.create', $project) }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                Add Phase
            </a>
            <a href="{{ route('admin.project.projects.tasks.create', $project) }}"
               class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                Add Task
            </a>
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
        <div id="gantt-chart-container" class="relative">
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
                        <th class="sticky left-0 bg-gray-50 z-10 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-64">
                            Task
                        </th>
                        <th class="sticky left-64 bg-gray-50 z-10 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-24">
                            Assigned To
                        </th>
                        <th class="sticky left-88 bg-gray-50 z-10 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-20">
                            Progress
                        </th>
                        <th class="sticky left-108 bg-gray-50 z-10 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-32">
                            Start
                        </th>
                        <th class="sticky left-140 bg-gray-50 z-10 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 w-32">
                            End
                        </th>
                        {{-- Timeline Header --}}
                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Timeline ({{ $timelineStart->format('M d') }} — {{ $timelineEnd->format('M d Y') }})
                        </th>
                    </tr>
                    {{-- Timeline Date Header Row --}}
                    <tr class="bg-gray-50">
                        <th class="sticky left-0 bg-gray-50 z-10 border-r border-gray-200"></th>
                        <th class="sticky left-64 bg-gray-50 z-10 border-r border-gray-200"></th>
                        <th class="sticky left-88 bg-gray-50 z-10 border-r border-gray-200"></th>
                        <th class="sticky left-108 bg-gray-50 z-10 border-r border-gray-200"></th>
                        <th class="sticky left-140 bg-gray-50 z-10 border-r border-gray-200"></th>
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
                            <td class="sticky left-0 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200">
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
                                                data-edit-url="{{ route('admin.project.projects.phases.edit', [$project, $phase]) }}"
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
                            <td class="sticky left-64 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="sticky left-88 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">{{ $phase->progress_actual }}%</td>
                            <td class="sticky left-108 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
                                <div class="space-y-0.5">
                                    @if($phase->start_date_plan) <div class="text-gray-500">P: {{ $phase->start_date_plan->format('d/m/Y') }}</div> @endif
                                    @if($phase->start_date_revise) <div class="text-orange-500">R: {{ $phase->start_date_revise->format('d/m/Y') }}</div> @endif
                                    @if($phase->start_date_actual) <div class="text-green-600">A: {{ $phase->start_date_actual->format('d/m/Y') }}</div> @endif
                                </div>
                            </td>
                            <td class="sticky left-140 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">
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
                                        </div>
                                    @endif
                                    {{-- Revise bar --}}
                                    @if($phaseReviseStartOffset !== null && $phaseReviseDuration !== null)
                                        <div class="gantt-bar absolute" data-start-offset="{{ $phaseReviseStartOffset }}" data-duration="{{ $phaseReviseDuration }}" data-bar-type="revise"
                                             style="left: {{ $phaseReviseStartOffset * $dayWidth }}px; top: 28px; width: {{ max($phaseReviseDuration * $dayWidth, 4) }}px; height: 16px; background-color: #fb923c; border: 1px solid #f97316; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                                             title="Revise: {{ $phase->start_date_revise->format('d M Y') }} — {{ $phase->end_date_revise->format('d M Y') }}">
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
                            <td class="sticky left-0 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200">
                                <span class="font-semibold text-gray-900">Standalone Tasks</span>
                            </td>
                            <td class="sticky left-64 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="sticky left-88 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="sticky left-108 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
                            <td class="sticky left-140 bg-gray-100 z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-500">—</td>
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
        ganttRenderHeader(zoomLevel);
        ganttUpdateBars(zoomLevel);
        ganttUpdateGridLines(zoomLevel);
        ganttUpdateTimelineAreas(zoomLevel);
        ganttUpdateTodayLine(zoomLevel);
        positionTodayLine();

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
</script>
</div>
