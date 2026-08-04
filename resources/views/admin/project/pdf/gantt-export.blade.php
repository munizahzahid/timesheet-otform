<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Gantt Chart - {{ $project->project_name }}</title>
<style>
@page { margin: 35mm; size: A4 landscape; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; line-height: 1.4; padding: 12px; }

/* Header */
.header { width: 100%; margin-bottom: 14px; border-bottom: 1.5px solid #333; padding-bottom: 10px; }
.header-left { float: left; width: 55%; }
.header-right { float: right; width: 45%; text-align: right; }
.clear { clear: both; }
.logo-img { max-height: 42px; width: auto; display: block; margin-bottom: 6px; }
.project-title { font-size: 13px; font-weight: bold; color: #000; margin-top: 4px; }
.meta-item { font-size: 8px; margin-bottom: 3px; }
.meta-label { font-weight: bold; color: #555; }

/* Gantt Table */
.gantt-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px; border: 1.5px solid #666; }
.gantt-table th, .gantt-table td { border: 1px solid #bbb; vertical-align: middle; }
.gantt-table th { background: #e8e8e8; font-weight: bold; text-align: center; padding: 3px 4px; font-size: 7px; color: #222; }
.gantt-table td { padding: 3px 4px; font-size: 7px; height: 20px; }
.gantt-table .col-no { width: 26px; text-align: center; }
.gantt-table .col-task { width: 240px; text-align: left; }
.gantt-table .col-assign { width: 150px; text-align: left; }
.gantt-table .col-timeline { padding: 0; }

.phase-row td { background: #d9d9d9; font-weight: bold; border-left: 4px solid #333; color: #000; }
.task-row td { background: #fff; }
.task-row:nth-child(even) td { background: #f7f7f7; }

/* Timeline cell container */
.timeline-cell { position: relative; width: 100%; height: 20px; overflow: hidden; }
.timeline-grid { position: absolute; top: 0; bottom: 0; border-right: 1px solid #e0e0e0; }

/* Bars */
.gantt-bar { position: absolute; height: 3px; border-radius: 1px; }
.bar-plan { background: #2563EB; top: 2px; }
.bar-revise { background: #F97316; top: 6px; }
.bar-actual { background: #16A34A; top: 10px; }
.bar-effective { background: #86EFAC; border: 1px dashed #16A34A; top: 14px; }

/* Legend */
.legend { margin-top: 14px; font-size: 7px; padding-top: 8px; border-top: 1px solid #ccc; }
.legend span { margin-right: 14px; }
.legend-box { display: inline-block; width: 8px; height: 8px; border-radius: 1px; vertical-align: middle; margin-right: 3px; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        @if($logoBase64)
            <img src="data:image/jpeg;base64,{{ $logoBase64 }}" class="logo-img" alt="Logo">
        @endif
        <div class="project-title">{{ $project->project_name }} ({{ $project->project_code ?? 'N/A' }})</div>
    </div>
    <div class="header-right">
        <div class="meta-item"><span class="meta-label">Date Last Update:</span> {{ $project->date_time_updated?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
        <div class="meta-item"><span class="meta-label">Prepared By:</span> {{ $project->updated_by ?? $project->project_manager ?? '-' }}</div>
        <div class="meta-item"><span class="meta-label">Checked By:</span> {{ $project->project_manager ?? '-' }}</div>
    </div>
    <div class="clear"></div>
</div>

@php
    $allDates = collect();
    foreach($phases as $phase) {
        foreach($phase->tasks as $task) {
            $eff = $effectiveDates[$task->id] ?? null;
            if ($eff && $eff['start_date']) $allDates->push($eff['start_date']->copy());
            if ($eff && $eff['end_date']) $allDates->push($eff['end_date']->copy());
            if ($task->start_date_plan) $allDates->push($task->start_date_plan->copy());
            if ($task->end_date_plan) $allDates->push($task->end_date_plan->copy());
            if ($task->start_date_actual) $allDates->push($task->start_date_actual->copy());
            if ($task->end_date_actual) $allDates->push($task->end_date_actual->copy());
            if ($task->start_date_revise) $allDates->push($task->start_date_revise->copy());
            if ($task->end_date_revise) $allDates->push($task->end_date_revise->copy());
        }
    }
    foreach($standaloneTasks as $task) {
        $eff = $effectiveDates[$task->id] ?? null;
        if ($eff && $eff['start_date']) $allDates->push($eff['start_date']->copy());
        if ($eff && $eff['end_date']) $allDates->push($eff['end_date']->copy());
        if ($task->start_date_plan) $allDates->push($task->start_date_plan->copy());
        if ($task->end_date_plan) $allDates->push($task->end_date_plan->copy());
        if ($task->start_date_actual) $allDates->push($task->start_date_actual->copy());
        if ($task->end_date_actual) $allDates->push($task->end_date_actual->copy());
        if ($task->start_date_revise) $allDates->push($task->start_date_revise->copy());
        if ($task->end_date_revise) $allDates->push($task->end_date_revise->copy());
    }
    if ($allDates->isEmpty()) {
        $timelineStart = now()->copy()->subDays(30)->startOfDay();
        $timelineEnd = now()->copy()->addDays(90)->startOfDay();
    } else {
        $timelineStart = $allDates->min()->copy()->subDays(7)->startOfDay();
        $timelineEnd = $allDates->max()->copy()->addDays(7)->startOfDay();
    }
    $totalDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);

    $timeBlocks = [];
    $leftPercent = 0;
    if ($zoom === 'day') {
        $current = $timelineStart->copy();
        while ($current <= $timelineEnd) {
            $wp = (1 / $totalDays) * 100;
            $timeBlocks[] = ['label' => $current->format('d'), 'startDate' => $current->copy(), 'endDate' => $current->copy(), 'days' => 1, 'widthPercent' => $wp, 'leftPercent' => $leftPercent];
            $leftPercent += $wp;
            $current->addDay();
        }
    } elseif ($zoom === 'week') {
        $current = $timelineStart->copy()->startOfWeek();
        $weekIndex = 1;
        while ($current <= $timelineEnd) {
            $blockEnd = $current->copy()->endOfWeek();
            if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy();
            $days = $current->diffInDays($blockEnd) + 1;
            $wp = ($days / $totalDays) * 100;
            $timeBlocks[] = ['label' => 'W' . $weekIndex, 'startDate' => $current->copy(), 'endDate' => $blockEnd->copy(), 'days' => $days, 'widthPercent' => $wp, 'leftPercent' => $leftPercent];
            $leftPercent += $wp;
            $current->addWeek();
            $weekIndex++;
        }
    } elseif ($zoom === 'month') {
        $current = $timelineStart->copy()->startOfMonth();
        while ($current <= $timelineEnd) {
            $blockEnd = $current->copy()->endOfMonth();
            if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy();
            $days = $current->diffInDays($blockEnd) + 1;
            $wp = ($days / $totalDays) * 100;
            $timeBlocks[] = ['label' => $current->format('M'), 'startDate' => $current->copy(), 'endDate' => $blockEnd->copy(), 'days' => $days, 'widthPercent' => $wp, 'leftPercent' => $leftPercent];
            $leftPercent += $wp;
            $current->addMonth()->startOfMonth();
        }
    } else {
        $current = $timelineStart->copy()->startOfYear();
        while ($current <= $timelineEnd) {
            $blockEnd = $current->copy()->endOfYear();
            if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy();
            $days = $current->diffInDays($blockEnd) + 1;
            $wp = ($days / $totalDays) * 100;
            $timeBlocks[] = ['label' => $current->format('Y'), 'startDate' => $current->copy(), 'endDate' => $blockEnd->copy(), 'days' => $days, 'widthPercent' => $wp, 'leftPercent' => $leftPercent];
            $leftPercent += $wp;
            $current->addYear()->startOfYear();
        }
    }

    $headerRows = [];

    // Year row (always, except for year zoom where blocks are years)
    if ($zoom !== 'year') {
        $yearGroups = [];
        $current = $timelineStart->copy()->startOfYear();
        while ($current <= $timelineEnd) {
            $yStart = $current->copy();
            $yEnd = $current->copy()->endOfYear();
            if ($yEnd > $timelineEnd) $yEnd = $timelineEnd->copy();
            $blockCount = 0;
            foreach ($timeBlocks as $block) { if ($block['startDate'] >= $yStart && $block['startDate'] <= $yEnd) $blockCount++; }
            if ($blockCount > 0) $yearGroups[] = ['label' => $current->format('Y'), 'colspan' => $blockCount];
            $current->addYear()->startOfYear();
        }
        if (count($yearGroups) > 0) $headerRows[] = ['type' => 'year', 'groups' => $yearGroups];
    }

    // Month row (for day and week zoom)
    if ($zoom === 'day' || $zoom === 'week') {
        $monthGroups = [];
        $current = $timelineStart->copy()->startOfMonth();
        while ($current <= $timelineEnd) {
            $mStart = $current->copy()->startOfMonth();
            $mEnd = $current->copy()->endOfMonth();
            if ($mStart < $timelineStart) $mStart = $timelineStart->copy();
            if ($mEnd > $timelineEnd) $mEnd = $timelineEnd->copy();
            $blockCount = 0;
            foreach ($timeBlocks as $block) { if ($block['startDate'] >= $mStart && $block['startDate'] <= $mEnd) $blockCount++; }
            if ($blockCount > 0) $monthGroups[] = ['label' => $current->format('M'), 'colspan' => $blockCount];
            $current->addMonth()->startOfMonth();
        }
        if (count($monthGroups) > 0) $headerRows[] = ['type' => 'month', 'groups' => $monthGroups];
    }

    $timeColCount = count($timeBlocks);

    if (!function_exists('ganttBarStyle')) {
        function ganttBarStyle($startDate, $endDate, $timelineStart, $totalDays) {
            if (!$startDate || !$endDate) return '';
            $left = max(0, $timelineStart->diffInDays($startDate) / $totalDays * 100);
            $width = min(100 - $left, ($startDate->diffInDays($endDate) + 1) / $totalDays * 100);
            return 'left: ' . number_format($left, 2) . '%; width: ' . number_format(max(0.3, $width), 2) . '%;';
        }
    }
@endphp

<table class="gantt-table">
    <colgroup>
        <col class="col-no">
        <col class="col-task">
        <col class="col-assign">
        @foreach($timeBlocks as $block)
        <col>
        @endforeach
    </colgroup>
    <thead>
        @foreach($headerRows as $row)
        <tr>
            <th class="col-no"></th>
            <th class="col-task"></th>
            <th class="col-assign"></th>
            @foreach($row['groups'] as $group)
            <th colspan="{{ $group['colspan'] ?? 1 }}">{{ $group['label'] }}</th>
            @endforeach
        </tr>
        @endforeach
        <tr>
            <th class="col-no">No.</th>
            <th class="col-task">Task</th>
            <th class="col-assign">Assigned To</th>
            @foreach($timeBlocks as $block)
            <th>{{ $block['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>

@php $rowNum = 1; @endphp
@foreach($phases as $phase)
    <tr class="phase-row">
        <td colspan="{{ 3 + $timeColCount }}">{{ $phase->phase_name }}</td>
    </tr>
    @foreach($phase->tasks as $task)
        @php $eff = $effectiveDates[$task->id] ?? null; @endphp
        <tr class="task-row">
            <td class="col-no">{{ $rowNum++ }}</td>
            <td class="col-task">{{ $task->task_name }}</td>
            <td class="col-assign">{{ $task->assignedTo?->name ?? '-' }}</td>
            <td colspan="{{ $timeColCount }}" class="col-timeline">
                <div class="timeline-cell">
                    @foreach($timeBlocks as $block)
                        <div class="timeline-grid" style="left: {{ $block['leftPercent'] }}%; width: {{ $block['widthPercent'] }}%;"></div>
                    @endforeach
                    @if($visible['plan'] && $task->start_date_plan && $task->end_date_plan)
                        <div class="gantt-bar bar-plan" style="{{ ganttBarStyle($task->start_date_plan, $task->end_date_plan, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['revise'] && $task->start_date_revise && $task->end_date_revise)
                        <div class="gantt-bar bar-revise" style="{{ ganttBarStyle($task->start_date_revise, $task->end_date_revise, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['actual'] && $task->start_date_actual)
                        @php $ae = $task->end_date_actual ?? now(); @endphp
                        <div class="gantt-bar bar-actual" style="{{ ganttBarStyle($task->start_date_actual, $ae, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['actual'] && $eff && $eff['start_date'] && $eff['end_date'])
                        <div class="gantt-bar bar-effective" style="{{ ganttBarStyle($eff['start_date'], $eff['end_date'], $timelineStart, $totalDays) }}"></div>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
@endforeach

@if($standaloneTasks->count())
    <tr class="phase-row">
        <td colspan="{{ 3 + $timeColCount }}">Standalone Tasks</td>
    </tr>
    @foreach($standaloneTasks as $task)
        @php $eff = $effectiveDates[$task->id] ?? null; @endphp
        <tr class="task-row">
            <td class="col-no">{{ $rowNum++ }}</td>
            <td class="col-task">{{ $task->task_name }}</td>
            <td class="col-assign">{{ $task->assignedTo?->name ?? '-' }}</td>
            <td colspan="{{ $timeColCount }}" class="col-timeline">
                <div class="timeline-cell">
                    @foreach($timeBlocks as $block)
                        <div class="timeline-grid" style="left: {{ $block['leftPercent'] }}%; width: {{ $block['widthPercent'] }}%;"></div>
                    @endforeach
                    @if($visible['plan'] && $task->start_date_plan && $task->end_date_plan)
                        <div class="gantt-bar bar-plan" style="{{ ganttBarStyle($task->start_date_plan, $task->end_date_plan, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['revise'] && $task->start_date_revise && $task->end_date_revise)
                        <div class="gantt-bar bar-revise" style="{{ ganttBarStyle($task->start_date_revise, $task->end_date_revise, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['actual'] && $task->start_date_actual)
                        @php $ae = $task->end_date_actual ?? now(); @endphp
                        <div class="gantt-bar bar-actual" style="{{ ganttBarStyle($task->start_date_actual, $ae, $timelineStart, $totalDays) }}"></div>
                    @endif
                    @if($visible['actual'] && $eff && $eff['start_date'] && $eff['end_date'])
                        <div class="gantt-bar bar-effective" style="{{ ganttBarStyle($eff['start_date'], $eff['end_date'], $timelineStart, $totalDays) }}"></div>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
@endif

    </tbody>
</table>

<div class="legend">
    <span><span class="legend-box" style="background:#2563EB;"></span> Plan</span>
    <span><span class="legend-box" style="background:#F97316;"></span> Revise</span>
    <span><span class="legend-box" style="background:#16A34A;"></span> Actual</span>
    <span><span class="legend-box" style="background:#86EFAC;border:1px dashed #16A34A;"></span> Effective</span>
</div>

</body>
</html>
