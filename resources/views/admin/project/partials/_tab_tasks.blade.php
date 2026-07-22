@php
    $statusColumns = [
        'completed'   => 'Done',
        'in_progress' => 'Working on it',
        'on_hold'     => 'Stuck',
        'not_started' => 'Not Started',
    ];
    $statusHeaderColors = [
        'completed'   => 'bg-green-100',
        'in_progress' => 'bg-blue-100',
        'on_hold'     => 'bg-red-100',
        'not_started' => 'bg-gray-100',
    ];
    $statusBadgeColors = [
        'completed'   => 'bg-green-500',
        'in_progress' => 'bg-blue-500',
        'on_hold'     => 'bg-red-500',
        'not_started' => 'bg-gray-500',
    ];
    $groupedTasks = $tasks->groupBy(function ($task) {
        return $task->status ?? 'not_started';
    });
@endphp

<div class="p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Project Tasks</h3>
            <p class="text-sm text-gray-500 mt-1">{{ $tasks->count() }} task{{ $tasks->count() != 1 ? 's' : '' }}</p>
        </div>
        <a href="{{ route('admin.project.projects.tasks.create', $project) }}"
           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
            Add Task
        </a>
    </div>

    @if($tasks->isEmpty())
        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm text-gray-500 mt-2">No tasks yet.</p>
            <p class="text-xs text-gray-400 mt-1">Add a task to get started.</p>
        </div>
    @else
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach($statusColumns as $statusKey => $statusLabel)
                @php
                    $columnTasks = $groupedTasks->get($statusKey, collect())
                        ->sortBy(fn($task) => $task->phase_id ?? PHP_INT_MAX);
                @endphp
                <div class="bg-gray-50 rounded-lg border border-gray-200 flex flex-col flex-1 min-w-0 min-w-[280px] max-w-[320px]">
                    <div class="px-3 py-2 border-b border-gray-200 flex items-center justify-between {{ $statusHeaderColors[$statusKey] }}">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $statusBadgeColors[$statusKey] }}"></span>
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide">{{ $statusLabel }}</h4>
                        </div>
                        <span class="text-xs font-semibold text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200">{{ $columnTasks->count() }}</span>
                    </div>
                    <div class="p-3 space-y-3 flex-1">
                        @php $lastPhaseId = null; @endphp
                        @forelse($columnTasks as $task)
                            @php
                                $taskPhase = $task->phase;
                                $currentPhaseId = optional($taskPhase)->id;
                            @endphp
                            @if($loop->first || ($currentPhaseId !== ($lastPhaseId ?? null)))
                                <div class="flex items-center gap-2 my-1">
                                    <div class="flex-1 h-px bg-gray-300"></div>
                                    <span class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">{{ $taskPhase->phase_name ?? 'No Phase' }}</span>
                                    <div class="flex-1 h-px bg-gray-300"></div>
                                </div>
                            @endif
                            @php $lastPhaseId = $currentPhaseId; @endphp
                            <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm hover:shadow-md transition relative">
                                <div class="flex items-start justify-between mb-3 gap-2">
                                    <a href="{{ route('admin.project.projects.tasks.show', [$project, $task]) }}" class="text-sm font-semibold text-gray-900 line-clamp-2 flex-1 hover:text-indigo-600">{{ $task->task_name }}</a>
                                    <a href="{{ route('admin.project.projects.tasks.edit', [$project, $task]) }}" class="text-gray-400 hover:text-indigo-600 flex-shrink-0 p-1 rounded hover:bg-gray-100" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <div class="relative kanban-menu flex-shrink-0">
                                        <button type="button" class="kanban-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-1 rounded hover:bg-gray-100">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>
                                        <div class="kanban-menu-dropdown hidden absolute right-0 mt-1 w-28 bg-white rounded-md shadow-lg border border-gray-200 z-20 py-1">
                                            <a href="{{ route('admin.project.projects.tasks.edit', [$project, $task]) }}" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">Edit</a>
                                            <form action="{{ route('admin.project.projects.tasks.destroy', [$project, $task]) }}" method="POST" onsubmit="return confirm('Delete this task?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-gray-100">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-3 flex-wrap">
                                    <div class="relative status-dropdown-wrap flex-shrink-0">
                                        <button type="button"
                                                class="status-badge-btn inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium text-white {{ $statusBadgeColors[$task->status ?? 'not_started'] }} hover:opacity-80 cursor-pointer"
                                                data-task-id="{{ $task->id }}"
                                                data-current-status="{{ $task->status ?? 'not_started' }}">
                                            {{ ucfirst(str_replace('_', ' ', $task->status ?? 'Not Started')) }}
                                            <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div class="status-dropdown hidden absolute left-0 mt-1 w-36 bg-white rounded-md shadow-lg border border-gray-200 z-30 py-1">
                                            @php
                                                $statusOptions = [
                                                    'completed' => 'Done',
                                                    'in_progress' => 'Working on it',
                                                    'on_hold' => 'Stuck',
                                                    'not_started' => 'Not Started',
                                                ];
                                            @endphp
                                            @foreach($statusOptions as $value => $label)
                                                <button type="button"
                                                        class="status-option-btn w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-indigo-50 flex items-center gap-2"
                                                        data-task-id="{{ $task->id }}"
                                                        data-status="{{ $value }}"
                                                        data-url="{{ route('admin.project.projects.tasks.inline-update', [$project, $task]) }}">
                                                    <span class="w-2 h-2 rounded-full {{ $statusBadgeColors[$value] }}"></span>
                                                    {{ $label }}
                                                    @if(($task->status ?? 'not_started') === $value)
                                                        <svg class="w-3 h-3 ml-auto text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="relative date-picker-wrap flex-shrink-0">
                                        <button type="button"
                                                class="date-badge-btn inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-1 rounded-full hover:bg-gray-200 cursor-pointer"
                                                data-task-id="{{ $task->id }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="date-badge-label">{{ $task->end_date_revise ? \Carbon\Carbon::parse($task->end_date_revise)->format('M d') : ($task->end_date_plan ? \Carbon\Carbon::parse($task->end_date_plan)->format('M d') : 'Set date') }}</span>
                                        </button>
                                        <div class="date-picker-popover hidden absolute left-0 mt-1 bg-white rounded-md shadow-lg border border-gray-200 z-30 p-2">
                                            <input type="date"
                                                   class="date-picker-input text-xs border border-gray-300 rounded px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                                   data-task-id="{{ $task->id }}"
                                                   data-url="{{ route('admin.project.projects.tasks.inline-update', [$project, $task]) }}"
                                                   value="{{ $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : '' }}" />
                                        </div>
                                    </div>

                                    <div class="relative weight-input-wrap flex-shrink-0">
                                        <button type="button"
                                                class="weight-badge-btn inline-flex items-center gap-0.5 text-[11px] text-gray-500 bg-gray-100 px-2 py-1 rounded-full hover:bg-gray-200 cursor-pointer"
                                                data-task-id="{{ $task->id }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 7v3m6-3v3M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                                            </svg>
                                            <span class="weight-badge-label">{{ $task->weight ?? 0 }}%</span>
                                        </button>
                                        <div class="weight-popover hidden absolute left-0 mt-1 bg-white rounded-md shadow-lg border border-gray-200 z-30 p-2">
                                            <input type="number"
                                                   min="0"
                                                   max="100"
                                                   class="weight-input-field w-16 text-xs border border-gray-300 rounded px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                                   data-task-id="{{ $task->id }}"
                                                   data-url="{{ route('admin.project.projects.tasks.inline-update', [$project, $task]) }}"
                                                   value="{{ $task->weight ?? 0 }}" />
                                            <p class="text-[10px] text-gray-400 mt-1">Max 100% per phase</p>
                                        </div>
                                    </div>
                                </div>

                                @if($task->remarks)
                                    <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ $task->remarks }}</p>
                                @endif

                                <div class="flex items-center gap-2 mb-3">
                                    <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 rounded-full" style="width: {{ $task->progress_actual }}%; background-color: #22c55e;"></div>
                                    </div>
                                    <span class="text-xs text-gray-600">{{ $task->progress_actual }}%</span>
                                </div>

                                @php
                                    $effective = $effectiveDates[$task->id] ?? [];
                                    $planDelay = $effective['plan_delay_days'] ?? 0;
                                @endphp

                                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                    @if($task->assignedTo)
                                        <a href="{{ route('admin.project.assigned-tasks', $task->assignedTo) }}" class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-600">
                                            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-[11px] font-bold text-indigo-600">
                                                {{ substr($task->assignedTo->name, 0, 1) }}
                                            </div>
                                            <span>{{ $task->assignedTo->name }}</span>
                                        </a>
                                    @else
                                        <span class="flex items-center gap-1.5 text-xs text-gray-400">
                                            <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <span>Unassigned</span>
                                        </span>
                                    @endif
                                    @if($planDelay > 0)
                                        <span class="text-red-600 font-semibold text-xs">{{ $planDelay }}d delay</span>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        <button type="button"
                                                class="comment-toggle-btn flex items-center gap-1 text-xs text-gray-400 hover:text-indigo-600 focus:outline-none"
                                                data-task-id="{{ $task->id }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                            </svg>
                                            <span>{{ $task->comments_count }}</span>
                                        </button>
                                        <button type="button"
                                                class="attachment-toggle-btn flex items-center gap-1 text-xs text-gray-400 hover:text-indigo-600 focus:outline-none"
                                                data-task-id="{{ $task->id }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            <span>{{ $task->attachments_count }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="comment-section hidden mt-3 space-y-2" data-task-id="{{ $task->id }}">
                                    @if($task->comments->isNotEmpty())
                                        <div class="space-y-2 max-h-40 overflow-y-auto">
                                            @foreach($task->comments as $comment)
                                                <div class="bg-gray-50 rounded p-2 text-xs">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="font-semibold text-gray-700">{{ $comment->user->name ?? 'Unknown' }}</span>
                                                        <span class="text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="text-gray-600 whitespace-pre-wrap">{{ $comment->comment }}</p>
                                                    @if($comment->user_id === auth()->id())
                                                        <form action="{{ route('admin.project.projects.tasks.comments.destroy', [$project, $task, $comment]) }}" method="POST" class="mt-1">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-500 hover:text-red-700 text-[10px]">Delete</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <form action="{{ route('admin.project.projects.tasks.comments.store', [$project, $task]) }}?{{ request()->getQueryString() }}" method="POST" class="flex flex-col gap-1">
                                        @csrf
                                        <input type="hidden" name="view" value="{{ request('view') }}">
                                        <textarea name="comment" rows="2" placeholder="Add a comment..." class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 resize-none" required></textarea>
                                        <button type="submit" class="self-end px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">Post</button>
                                    </form>
                                </div>
                                <div class="attachment-section hidden mt-3 space-y-2" data-task-id="{{ $task->id }}">
                                    @if($task->attachments->isNotEmpty())
                                        <div class="space-y-1 max-h-40 overflow-y-auto">
                                            @foreach($task->attachments as $attachment)
                                                <div class="bg-gray-50 rounded p-2 text-xs flex items-center justify-between gap-2">
                                                    <a href="{{ route('admin.project.projects.tasks.attachments.show', [$project, $task, $attachment]) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 truncate" title="{{ $attachment->file_name }}">
                                                        {{ $attachment->file_name }}
                                                    </a>
                                                    @if($attachment->user_id === auth()->id())
                                                        <form action="{{ route('admin.project.projects.tasks.attachments.destroy', [$project, $task, $attachment]) }}?{{ request()->getQueryString() }}" method="POST" onsubmit="return confirm('Delete this file?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-500 hover:text-red-700 text-[10px] whitespace-nowrap">Delete</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <form action="{{ route('admin.project.projects.tasks.attachments.store', [$project, $task]) }}?{{ request()->getQueryString() }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-1">
                                        @csrf
                                        <input type="hidden" name="view" value="{{ request('view') }}">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="file" name="attachment" class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required>
                                        </label>
                                        <button type="submit" class="self-end px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">Upload</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <p class="text-xs text-gray-400">No tasks</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Kanban 3-dot menu toggle
            document.querySelectorAll('.kanban-menu-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = this.closest('.kanban-menu').querySelector('.kanban-menu-dropdown');
                    document.querySelectorAll('.kanban-menu-dropdown').forEach(function(d) {
                        if (d !== dropdown) d.classList.add('hidden');
                    });
                    dropdown.classList.toggle('hidden');
                });
            });

            document.addEventListener('click', function() {
                document.querySelectorAll('.kanban-menu-dropdown').forEach(function(d) {
                    d.classList.add('hidden');
                });
            });

            // Comment toggle
            document.querySelectorAll('.comment-toggle-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.dataset.taskId;
                    const section = document.querySelector('.comment-section[data-task-id="' + taskId + '"]');
                    if (section) {
                        section.classList.toggle('hidden');
                    }
                });
            });

            // Attachment toggle
            document.querySelectorAll('.attachment-toggle-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.dataset.taskId;
                    const section = document.querySelector('.attachment-section[data-task-id="' + taskId + '"]');
                    if (section) {
                        section.classList.toggle('hidden');
                    }
                });
            });

            // Helper: close all popovers
            function closeAllPopovers() {
                document.querySelectorAll('.status-dropdown, .date-picker-popover, .weight-popover').forEach(function(p) {
                    p.classList.add('hidden');
                });
            }

            // Status dropdown toggle
            document.querySelectorAll('.status-badge-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    closeAllPopovers();
                    const dropdown = this.closest('.status-dropdown-wrap').querySelector('.status-dropdown');
                    dropdown.classList.toggle('hidden');
                });
            });

            // Status option click - AJAX update
            document.querySelectorAll('.status-option-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const url = this.dataset.url;
                    const status = this.dataset.status;
                    const taskId = this.dataset.taskId;
                    const wrap = this.closest('.status-dropdown-wrap');
                    const badgeBtn = wrap.querySelector('.status-badge-btn');
                    const statusColors = {
                        'completed': 'bg-green-500',
                        'in_progress': 'bg-blue-500',
                        'on_hold': 'bg-yellow-500',
                        'not_started': 'bg-gray-500',
                        'cancelled': 'bg-red-500',
                    };
                    const statusLabels = {
                        'completed': 'Done',
                        'in_progress': 'Working on it',
                        'on_hold': 'Stuck',
                        'not_started': 'Not Started',
                        'cancelled': 'Cancelled',
                    };

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ status: status }),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            badgeBtn.className = 'status-badge-btn inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium text-white ' + (statusColors[status] || 'bg-gray-500') + ' hover:opacity-80 cursor-pointer';
                            badgeBtn.setAttribute('data-current-status', status);
                            badgeBtn.innerHTML = statusLabels[status] + ' <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
                            closeAllPopovers();
                        }
                    })
                    .catch(function() {
                        alert('Failed to update status. Please try again.');
                        closeAllPopovers();
                    });
                });
            });

            // Date picker toggle
            document.querySelectorAll('.date-badge-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    closeAllPopovers();
                    const popover = this.closest('.date-picker-wrap').querySelector('.date-picker-popover');
                    popover.classList.toggle('hidden');
                    if (!popover.classList.contains('hidden')) {
                        const input = popover.querySelector('.date-picker-input');
                        setTimeout(function() { input.focus(); }, 50);
                    }
                });
            });

            // Date picker change - AJAX update
            document.querySelectorAll('.date-picker-input').forEach(function(input) {
                input.addEventListener('change', function(e) {
                    e.stopPropagation();
                    const url = this.dataset.url;
                    const dateValue = this.value;
                    const wrap = this.closest('.date-picker-wrap');
                    const label = wrap.querySelector('.date-badge-label');

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ end_date_revise: dateValue || null }),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            if (data.task.end_date_revise) {
                                label.textContent = data.task.end_date_revise;
                            } else {
                                label.textContent = 'Set date';
                            }
                            closeAllPopovers();
                        }
                    })
                    .catch(function() {
                        alert('Failed to update date. Please try again.');
                        closeAllPopovers();
                    });
                });
                input.addEventListener('click', function(e) { e.stopPropagation(); });
            });

            // Weight input toggle
            document.querySelectorAll('.weight-badge-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    closeAllPopovers();
                    const popover = this.closest('.weight-input-wrap').querySelector('.weight-popover');
                    popover.classList.toggle('hidden');
                    if (!popover.classList.contains('hidden')) {
                        const input = popover.querySelector('.weight-input-field');
                        setTimeout(function() { input.focus(); input.select(); }, 50);
                    }
                });
            });

            // Weight input change - AJAX update
            document.querySelectorAll('.weight-input-field').forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.blur();
                    }
                });
                input.addEventListener('blur', function(e) {
                    e.stopPropagation();
                    const url = this.dataset.url;
                    const weightValue = parseInt(this.value, 10);
                    const wrap = this.closest('.weight-input-wrap');
                    const label = wrap.querySelector('.weight-badge-label');

                    if (isNaN(weightValue) || weightValue < 0 || weightValue > 100) {
                        alert('Weight must be between 0 and 100.');
                        closeAllPopovers();
                        return;
                    }

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ weight: weightValue }),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            label.textContent = data.task.weight + '%';
                            closeAllPopovers();
                        } else if (data.message) {
                            alert(data.message);
                            closeAllPopovers();
                        }
                    })
                    .catch(function() {
                        alert('Failed to update weight. Please try again.');
                        closeAllPopovers();
                    });
                });
                input.addEventListener('click', function(e) { e.stopPropagation(); });
            });

            // Close popovers on outside click
            document.addEventListener('click', function() {
                closeAllPopovers();
            });
        });
    </script>
@endpush
