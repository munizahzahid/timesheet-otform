@php
    $effective = isset($effectiveDates) && isset($effectiveDates[$task->id]) ? $effectiveDates[$task->id] : null;

    // Effective start/end dates (considering dependencies)
    $effectiveStart = $effective['start_date'] ?? null;
    $effectiveEnd = $effective['end_date'] ?? null;

    // Calculate bar positions
    $planStartOffset = $task->start_date_plan ? $timelineStart->diffInDays($task->start_date_plan) : null;
    $planDuration = $task->start_date_plan && $task->end_date_plan ? $task->start_date_plan->diffInDays($task->end_date_plan) + 1 : null;

    // Actual lane: use the task's actual dates directly (not resolved from dependencies)
    // The actual dates should reflect what the user has set, not dependency calculations
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

    // Progress percentages to display inside bars
    $today = now('Asia/Kuala_Lumpur')->copy()->startOfDay();
    $planProgress = 0;
    if ($task->start_date_plan && $task->end_date_plan) {
        if ($today->lte($task->start_date_plan)) {
            $planProgress = 0;
        } elseif ($today->gte($task->end_date_plan)) {
            $planProgress = 100;
        } else {
            $total = $task->start_date_plan->diffInDays($task->end_date_plan);
            $elapsed = $task->start_date_plan->diffInDays($today);
            $planProgress = min(100, max(0, round(($elapsed / max(1, $total)) * 100)));
        }
    }
    $reviseProgress = null;
    if ($task->start_date_revise && $task->end_date_revise) {
        if ($today->lte($task->start_date_revise)) {
            $reviseProgress = 0;
        } elseif ($today->gte($task->end_date_revise)) {
            $reviseProgress = 100;
        } else {
            $total = $task->start_date_revise->diffInDays($task->end_date_revise);
            $elapsed = $task->start_date_revise->diffInDays($today);
            $reviseProgress = min(100, max(0, round(($elapsed / max(1, $total)) * 100)));
        }
    }
    $actualProgress = $task->progress_actual ?? 0;
@endphp

<tr class="hover:bg-gray-50 task-row gantt-draggable-row task-phase-{{ $task->phase_id ?? 'standalone' }}" data-phase-id="{{ $task->phase_id ?? '' }}" data-task-id="{{ $task->id }}" data-task-order="{{ $task->task_order }}" data-predecessor-id="{{ $task->predecessor_task_id }}" data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}" data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}" data-task-name="{{ $task->task_name }}">
    <td class="sticky left-0 bg-white z-40 px-4 py-2 border-r border-gray-200 pl-8">
        <div class="flex items-center gap-2">
            <div class="gantt-drag-handle text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100 cursor-grab" draggable="true" title="Drag to reorder">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="9" cy="6" r="2"/>
                    <circle cx="15" cy="6" r="2"/>
                    <circle cx="9" cy="12" r="2"/>
                    <circle cx="15" cy="12" r="2"/>
                    <circle cx="9" cy="18" r="2"/>
                    <circle cx="15" cy="18" r="2"/>
                </svg>
            </div>
            <div class="gantt-menu">
                <button type="button" class="gantt-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100" title="Subtask actions"
                        data-edit-type="task"
                        data-edit-id="{{ $task->id }}"
                        data-update-url="{{ route('admin.project.projects.tasks.update', [$project, $task]) }}"
                        data-delete-action="{{ route('admin.project.projects.tasks.destroy', [$project, $task]) . '?' . (request()->getQueryString() ?: 'tab=schedule') }}"
                        data-delete-confirm="Delete this subtask?"
                        data-task-name="{{ $task->task_name }}"
                        data-task-order="{{ $task->task_order }}"
                        data-weight="{{ $task->weight ?? 0 }}"
                        data-phase-id="{{ $task->phase_id ?? '' }}"
                        data-status="{{ $task->status ?? 'not_started' }}"
                        data-assigned-to="{{ $task->assigned_to ?? '' }}"
                        data-predecessor-task-id="{{ $task->predecessor_task_id ?? '' }}"
                        data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}"
                        data-start-date-plan="{{ $task->start_date_plan?->format('Y-m-d') ?? '' }}"
                        data-end-date-plan="{{ $task->end_date_plan?->format('Y-m-d') ?? '' }}"
                        data-start-date-actual="{{ $task->start_date_actual?->format('Y-m-d') ?? '' }}"
                        data-end-date-actual="{{ $task->end_date_actual?->format('Y-m-d') ?? '' }}"
                        data-start-date-revise="{{ $task->start_date_revise?->format('Y-m-d') ?? '' }}"
                        data-end-date-revise="{{ $task->end_date_revise?->format('Y-m-d') ?? '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ $task->task_name }}</span>
        </div>
    </td>
    <td class="sticky left-64 bg-white z-40 px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        {{ $task->assignedTo->name ?? '—' }}
    </td>
    <td class="bg-white px-4 py-2 border-r border-gray-200 text-xs text-gray-600 text-center w-16">
        <div class="gantt-weight-cell cursor-pointer hover:bg-indigo-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition inline-block min-w-[2rem]"
             data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
             data-task-name="{{ $task->task_name }}"
             data-weight="{{ $task->weight ?? 0 }}">
            {{ $task->weight ?? '' }}
        </div>
    </td>
    <td class="bg-white px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="space-y-0.5">
            <div class="text-blue-600">P: {{ $planProgress }}%</div>
            <div class="text-green-600">A: {{ $task->progress_actual }}%</div>
            @if(isset($effective['plan_delay_days']) && $effective['plan_delay_days'] > 0)
                <div class="text-red-500 font-semibold whitespace-nowrap" title="Plan delay: actual/today exceeds plan end date">
                    {{ $effective['plan_delay_days'] }}d late
                </div>
            @endif
            @if(isset($effective['dependency_shift_days']) && $effective['dependency_shift_days'] > 0)
                <div class="text-orange-600 font-semibold whitespace-nowrap" title="Dependency shift: plan start vs effective start">
                    +{{ $effective['dependency_shift_days'] }}d shift
                </div>
            @endif
        </div>
    </td>
    <td class="bg-white px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="space-y-0.5">
            @if($task->start_date_plan)
                <div class="gantt-date-cell cursor-pointer hover:bg-blue-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="start_date_plan"
                     data-date="{{ $task->start_date_plan->format('Y-m-d') }}"
                     data-pair-date="{{ $task->end_date_plan?->format('Y-m-d') }}"
                     data-predecessor-id="{{ $task->predecessor_task_id }}"
                     data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}">
                    <span class="text-gray-500">P: {{ $task->start_date_plan->format('d/m/Y') }}</span>
                </div>
            @endif
            @if($task->start_date_revise)
                <div class="gantt-date-cell cursor-pointer hover:bg-orange-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="start_date_revise"
                     data-date="{{ $task->start_date_revise->format('Y-m-d') }}"
                     data-pair-date="{{ $task->end_date_revise?->format('Y-m-d') }}"
                     data-predecessor-id="{{ $task->predecessor_task_id }}"
                     data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}">
                    <span class="text-orange-500">R: {{ $task->start_date_revise->format('d/m/Y') }}</span>
                </div>
            @endif
            @if($task->start_date_actual)
                <div class="gantt-date-cell cursor-pointer hover:bg-green-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="start_date_actual"
                     data-date="{{ $task->start_date_actual->format('Y-m-d') }}"
                     data-pair-date="{{ $task->end_date_actual?->format('Y-m-d') }}"
                     data-predecessor-id="{{ $task->predecessor_task_id }}"
                     data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}">
                    <span class="text-green-600">A: {{ $task->start_date_actual->format('d/m/Y') }}</span>
                </div>
            @else
                <div class="py-0.5">
                    <button type="button" class="gantt-start-actual-btn text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded hover:bg-green-200 transition"
                            data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                            data-task-name="{{ $task->task_name }}"
                            data-predecessor-id="{{ $task->predecessor_task_id }}"
                            data-dependency-type="{{ $task->dependency_type ?? 'end_to_start' }}"
                            data-predecessor-actual-end="{{ $task->predecessorTask?->end_date_actual?->format('Y-m-d') }}"
                            data-predecessor-actual-start="{{ $task->predecessorTask?->start_date_actual?->format('Y-m-d') }}">
                        Start
                    </button>
                </div>
            @endif
        </div>
    </td>
    <td class="bg-white px-4 py-2 border-r border-gray-200 text-xs text-gray-600">
        <div class="space-y-0.5">
            @if($task->end_date_plan)
                <div class="gantt-date-cell cursor-pointer hover:bg-blue-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="end_date_plan"
                     data-date="{{ $task->end_date_plan->format('Y-m-d') }}"
                     data-pair-date="{{ $task->start_date_plan?->format('Y-m-d') }}">
                    <span class="text-gray-500">P: {{ $task->end_date_plan->format('d/m/Y') }}</span>
                </div>
            @endif
            @if($task->end_date_revise)
                <div class="gantt-date-cell cursor-pointer hover:bg-orange-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="end_date_revise"
                     data-date="{{ $task->end_date_revise->format('Y-m-d') }}"
                     data-pair-date="{{ $task->start_date_revise?->format('Y-m-d') }}">
                    <span class="text-orange-500">R: {{ $task->end_date_revise->format('d/m/Y') }}</span>
                </div>
            @endif
            @if($task->end_date_actual)
                <div class="gantt-date-cell cursor-pointer hover:bg-green-50 rounded px-1 py-0.5 -mx-1 -my-0.5 transition"
                     data-update-url="{{ route('admin.project.projects.tasks.inline-update', ['project' => $project, 'task' => $task]) }}"
                     data-task-name="{{ $task->task_name }}"
                     data-field="end_date_actual"
                     data-date="{{ $task->end_date_actual->format('Y-m-d') }}"
                     data-pair-date="{{ $task->start_date_actual?->format('Y-m-d') }}">
                    <span class="text-green-600">A: {{ $task->end_date_actual->format('d/m/Y') }}</span>
                </div>
            @endif
        </div>
    </td>
    <td class="px-0 py-2 bg-white">
        {{-- 3-lane timeline --}}
        <div class="gantt-timeline-area relative" style="width: {{ $totalDays * $dayWidth }}px; min-width: 600px; height: 70px; border-left: 1px solid #e5e7eb;">
            {{-- Daily grid lines --}}
            @for($i = 0; $i <= $totalDays; $i++)
                <div class="gantt-grid-line absolute" data-day-offset="{{ $i }}" data-day-date="{{ $timelineStart->copy()->addDays($i)->format('Y-m-d') }}" style="left: {{ $i * $dayWidth }}px; top: 0; bottom: 0; width: 1px; background-color: #e5e7eb;"></div>
            @endfor

            {{-- Plan lane (row 1) --}}
            <div class="gantt-lane absolute" data-bar-type="plan" style="left: 0; right: 0; top: 4px; height: 18px; background-color: rgba(59, 130, 246, 0.08); border-radius: 6px;"></div>
            @if($planStartOffset !== null && $planDuration !== null)
                <div class="gantt-bar absolute gantt-resizable" data-task-id="{{ $task->id }}" data-start-offset="{{ $planStartOffset }}" data-duration="{{ $planDuration }}" data-bar-type="plan"
                     style="left: {{ $planStartOffset * $dayWidth }}px; top: 4px; width: {{ max($planDuration * $dayWidth, 4) }}px; height: 18px; background-color: rgba(59, 130, 246, 0.3); border: 1px solid #2563eb; border-radius: 6px; z-index: 10; box-shadow: 0 2px 5px rgba(37,99,235,0.25); overflow: hidden;"
                     title="Plan: {{ $task->start_date_plan->format('d M Y') }} — {{ $task->end_date_plan->format('d M Y') }} ({{ $planProgress }}%)">
                    {{-- Progress fill --}}
                    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $planProgress }}%; background-color: #3b82f6; transition: width 0.3s ease;"></div>
                    <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none" style="z-index: 5; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $planProgress }}%</span>
                    <div class="gantt-dot gantt-dot-left" data-dot-side="left" data-task-id="{{ $task->id }}" data-bar-type="plan" title="Drag to create dependency"></div>
                    <div class="gantt-dot gantt-dot-right" data-dot-side="right" data-task-id="{{ $task->id }}" data-bar-type="plan" title="Drag to create dependency"></div>
                    <div class="gantt-resize-handle gantt-resize-handle-left" data-resize-side="left"></div>
                    <div class="gantt-resize-handle gantt-resize-handle-right" data-resize-side="right"></div>
                </div>
            @endif

            {{-- Revise lane (row 2) --}}
            <div class="gantt-lane absolute" data-bar-type="revise" style="left: 0; right: 0; top: 26px; height: 18px; background-color: rgba(251, 146, 60, 0.08); border-radius: 6px;"></div>
            @if($reviseStartOffset !== null && $reviseDuration !== null)
                <div class="gantt-bar absolute gantt-resizable" data-task-id="{{ $task->id }}" data-start-offset="{{ $reviseStartOffset }}" data-duration="{{ $reviseDuration }}" data-bar-type="revise"
                     style="left: {{ $reviseStartOffset * $dayWidth }}px; top: 26px; width: {{ max($reviseDuration * $dayWidth, 4) }}px; height: 18px; background-color: rgba(249, 115, 22, 0.3); border: 1px solid #ea580c; border-radius: 6px; z-index: 10; box-shadow: 0 2px 5px rgba(234,88,12,0.25); overflow: hidden;"
                     title="Revise: {{ $task->start_date_revise->format('d M Y') }} — {{ $task->end_date_revise->format('d M Y') }} ({{ $reviseProgress }}%)">
                    {{-- Progress fill --}}
                    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $reviseProgress }}%; background-color: #f97316; transition: width 0.3s ease;"></div>
                    <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none" style="z-index: 5; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $reviseProgress }}%</span>
                    <div class="gantt-dot gantt-dot-left" data-dot-side="left" data-task-id="{{ $task->id }}" data-bar-type="revise" title="Drag to create dependency"></div>
                    <div class="gantt-dot gantt-dot-right" data-dot-side="right" data-task-id="{{ $task->id }}" data-bar-type="revise" title="Drag to create dependency"></div>
                    <div class="gantt-resize-handle gantt-resize-handle-left" data-resize-side="left"></div>
                    <div class="gantt-resize-handle gantt-resize-handle-right" data-resize-side="right"></div>
                </div>
            @endif

            {{-- Actual lane (row 3) --}}
            <div class="gantt-lane absolute" data-bar-type="actual" style="left: 0; right: 0; top: 48px; height: 18px; background-color: rgba(34, 197, 94, 0.08); border-radius: 6px;"></div>
            @if($actualStartOffset !== null && $actualDuration !== null)
                @php
                    $isOngoing = !$actualEnd;
                    $isCompleted = $actualProgress >= 100;
                    $actualTitleEnd = $isOngoing
                        ? now('Asia/Kuala_Lumpur')->copy()->startOfDay()->format('d M Y') . ' (ongoing)'
                        : ($actualEnd ? $actualEnd->format('d M Y') : now('Asia/Kuala_Lumpur')->copy()->startOfDay()->format('d M Y'));
                    $actualTitle = "Actual: " . $actualStart->format('d M Y') . " — " . $actualTitleEnd;
                    if ($isCompleted) {
                        $actualStyle = "background-color: #10b981; border: 1px solid #059669; border-radius: 6px; z-index: 10; box-shadow: 0 2px 5px rgba(5,150,105,0.25)";
                    } else {
                        $actualStyle = "background-color: #34d399; border: 1px solid #10b981; border-radius: 6px; z-index: 10; box-shadow: 0 2px 5px rgba(16,185,129,0.25)";
                    }
                @endphp
                <div class="gantt-bar absolute gantt-resizable gantt-effective-bar" data-task-id="{{ $task->id }}" data-start-offset="{{ $actualStartOffset }}" data-duration="{{ $actualDuration }}" data-bar-type="actual" data-progress="{{ $actualProgress }}"
                     style="left: {{ $actualStartOffset * $dayWidth }}px; top: 48px; width: {{ max($actualDuration * $dayWidth, 4) }}px; height: 18px; {{ $actualStyle }}"
                     title="{{ $actualTitle }} ({{ $actualProgress }}%)">
                    <span class="absolute inset-0 flex items-center justify-center text-[9px] font-medium text-white pointer-events-none gantt-progress-text" style="z-index: 5; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $actualProgress }}%</span>
                    <div class="gantt-dot gantt-dot-left" data-dot-side="left" data-task-id="{{ $task->id }}" data-bar-type="actual" title="Drag to create dependency"></div>
                    <div class="gantt-dot gantt-dot-right" data-dot-side="right" data-task-id="{{ $task->id }}" data-bar-type="actual" title="Drag to create dependency"></div>
                    <div class="gantt-resize-handle gantt-resize-handle-left" data-resize-side="left"></div>
                    @if($isCompleted)
                    <div class="gantt-resize-handle gantt-resize-handle-right" data-resize-side="right"></div>
                    @endif
                </div>
            @endif
        </div>
    </td>
</tr>
