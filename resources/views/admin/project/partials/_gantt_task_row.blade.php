@php
    $effective = isset($effectiveDates) && isset($effectiveDates[$task->id]) ? $effectiveDates[$task->id] : null;

    // Effective start/end dates (considering dependencies)
    $effectiveStart = $effective['start_date'] ?? null;
    $effectiveEnd = $effective['end_date'] ?? null;

    // Calculate bar positions
    $planStartOffset = $task->start_date_plan ? $timelineStart->diffInDays($task->start_date_plan) : null;
    $planDuration = $task->start_date_plan && $task->end_date_plan ? $task->start_date_plan->diffInDays($task->end_date_plan) + 1 : null;

    // Actual lane: show when actual start is entered; ongoing tasks run to today
    $actualStart = $task->start_date_actual;
    $actualEnd = $task->end_date_actual;
    $actualStartOffset = $actualStart ? $timelineStart->diffInDays($actualStart) : null;
    if ($actualStart) {
        $actualEndForBar = $actualEnd ?: now('Asia/Kuala_Lumpur')->copy()->startOfDay();
        // Ensure the bar has at least one day of visible width
        if ($actualEndForBar->lt($actualStart)) {
            $actualEndForBar = $actualStart->copy();
        }
        $actualDuration = $actualStart->diffInDays($actualEndForBar) + 1;
    } else {
        $actualDuration = null;
    }

    $reviseStartOffset = $task->start_date_revise ? $timelineStart->diffInDays($task->start_date_revise) : null;
    $reviseDuration = $task->start_date_revise && $task->end_date_revise ? $task->start_date_revise->diffInDays($task->end_date_revise) + 1 : null;

    $dayWidth = isset($dayWidth) ? $dayWidth : 30; // pixels per day
@endphp

<tr class="hover:bg-gray-50 task-row task-phase-{{ $task->phase_id ?? 'standalone' }}" data-phase-id="{{ $task->phase_id ?? '' }}">
    <td class="sticky left-0 bg-white z-10 px-4 py-2 border-r border-gray-200 pl-8">
        <div class="flex items-center gap-2">
            <div class="gantt-menu">
                <button type="button" class="gantt-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100" title="Task actions"
                        data-edit-url="{{ route('admin.project.projects.tasks.edit', [$project, $task]) }}"
                        data-delete-action="{{ route('admin.project.projects.tasks.destroy', [$project, $task]) }}"
                        data-delete-confirm="Delete this task?">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ $task->task_name }}</span>
        </div>
    </td>
    <td class="sticky left-64 bg-white z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        {{ $task->assignedTo->name ?? '—' }}
    </td>
    <td class="sticky left-88 bg-white z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="flex items-center gap-2">
            <span>{{ $task->progress_actual }}%</span>
            @if(isset($effective['plan_delay_days']) && $effective['plan_delay_days'] > 0)
                <span class="text-red-600 font-semibold whitespace-nowrap" title="Plan delay: actual/today exceeds plan end date">
                    {{ $effective['plan_delay_days'] }}d delay
                </span>
            @endif
            @if(isset($effective['dependency_shift_days']) && $effective['dependency_shift_days'] > 0)
                <span class="text-orange-600 font-semibold whitespace-nowrap" title="Dependency shift: plan start vs effective start">
                    +{{ $effective['dependency_shift_days'] }}d shift
                </span>
            @endif
        </div>
    </td>
    <td class="sticky left-108 bg-white z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="space-y-0.5">
            @if($task->start_date_plan) <div class="text-gray-500">P: {{ $task->start_date_plan->format('d/m/Y') }}</div> @endif
            @if($task->start_date_revise) <div class="text-orange-500">R: {{ $task->start_date_revise->format('d/m/Y') }}</div> @endif
            @if($task->start_date_actual) <div class="text-green-600">A: {{ $task->start_date_actual->format('d/m/Y') }}</div> @endif
        </div>
    </td>
    <td class="sticky left-140 bg-white z-10 px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="space-y-0.5">
            @if($task->end_date_plan) <div class="text-gray-500">P: {{ $task->end_date_plan->format('d/m/Y') }}</div> @endif
            @if($task->end_date_revise) <div class="text-orange-500">R: {{ $task->end_date_revise->format('d/m/Y') }}</div> @endif
            @if($task->end_date_actual) <div class="text-green-600">A: {{ $task->end_date_actual->format('d/m/Y') }}</div> @endif
        </div>
    </td>
    <td class="px-0 py-2 bg-white">
        {{-- 3-lane timeline --}}
        <div class="relative" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
            {{-- Daily grid lines --}}
            @for($i = 0; $i <= $totalDays; $i++)
                <div class="absolute" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
            @endfor

            {{-- Plan lane (row 1) --}}
            <div class="absolute" style="left: 0; right: 0; top: 4px; height: 18px; background-color: rgba(59, 130, 246, 0.08); border-radius: 4px;"></div>
            @if($planStartOffset !== null && $planDuration !== null)
                <div class="absolute" 
                     style="left: {{ $planStartOffset * $dayWidth }}px; top: 4px; width: {{ max($planDuration * $dayWidth, 4) }}px; height: 18px; background-color: #60a5fa; border: 1px solid #3b82f6; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                     title="Plan: {{ $task->start_date_plan->format('d M Y') }} — {{ $task->end_date_plan->format('d M Y') }}">
                </div>
            @endif

            {{-- Revise lane (row 2) --}}
            <div class="absolute" style="left: 0; right: 0; top: 26px; height: 18px; background-color: rgba(251, 146, 60, 0.08); border-radius: 4px;"></div>
            @if($reviseStartOffset !== null && $reviseDuration !== null)
                <div class="absolute" 
                     style="left: {{ $reviseStartOffset * $dayWidth }}px; top: 26px; width: {{ max($reviseDuration * $dayWidth, 4) }}px; height: 18px; background-color: #fb923c; border: 1px solid #f97316; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                     title="Revise: {{ $task->start_date_revise->format('d M Y') }} — {{ $task->end_date_revise->format('d M Y') }}">
                </div>
            @endif

            {{-- Actual lane (row 3) --}}
            <div class="absolute" style="left: 0; right: 0; top: 48px; height: 18px; background-color: rgba(34, 197, 94, 0.08); border-radius: 4px;"></div>
            @if($actualStartOffset !== null && $actualDuration !== null)
                @php
                    $isOngoing = !$actualEnd;
                    $actualTitleEnd = $isOngoing
                        ? now('Asia/Kuala_Lumpur')->copy()->startOfDay()->format('d M Y') . ' (ongoing)'
                        : $task->end_date_actual->format('d M Y');
                    $actualTitle = "Actual: " . $task->start_date_actual->format('d M Y') . " — " . $actualTitleEnd;
                    $actualStyle = $isOngoing
                        ? "background-color: #86efac; border: 1px solid #22c55e; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                        : "background-color: #22c55e; border: 1px solid #16a34a; border-radius: 4px; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);";
                @endphp
                <div class="absolute"
                     style="left: {{ $actualStartOffset * $dayWidth }}px; top: 48px; width: {{ max($actualDuration * $dayWidth, 4) }}px; height: 18px; {{ $actualStyle }}"
                     title="{{ $actualTitle }}">
                </div>
            @endif
        </div>
    </td>
</tr>
