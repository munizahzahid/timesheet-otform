<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tasks — {{ $project->project_name }}</h2>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('admin.project.projects.show', $project) }}?tab=tasks" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Project
            </a>
        </div>

        @php $isKanban = request('view') === 'kanban'; @endphp

        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Project Tasks</h3>
                <p class="text-sm text-gray-500">{{ $tasks->count() }} task{{ $tasks->count() != 1 ? 's' : '' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-md shadow-sm" role="group">
                    <a href="{{ route('admin.project.projects.tasks.index', $project) }}"
                       class="px-3 py-1.5 text-xs font-medium border rounded-l-md focus:z-10 focus:ring-2 focus:ring-indigo-500 {{ $isKanban ? 'text-gray-700 bg-white border-gray-200 hover:bg-gray-100' : 'text-indigo-700 bg-indigo-50 border-indigo-200' }}">
                        Table
                    </a>
                    <a href="{{ route('admin.project.projects.tasks.index', $project) }}?view=kanban"
                       class="px-3 py-1.5 text-xs font-medium border-t border-b border-r rounded-r-md focus:z-10 focus:ring-2 focus:ring-indigo-500 {{ $isKanban ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : 'text-gray-700 bg-white border-gray-200 hover:bg-gray-100' }}">
                        Kanban
                    </a>
                </div>
                <a href="{{ route('admin.project.projects.tasks.create', $project) }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    Add Task
                </a>
            </div>
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
            @php
                $statusColumns = [
                    'not_started' => 'To-do',
                    'in_progress' => 'In Progress',
                    'in_review'   => 'In Review',
                    'completed'   => 'Completed',
                ];
                $statusColors = [
                    'not_started' => 'bg-gray-100',
                    'in_progress' => 'bg-blue-100',
                    'in_review'   => 'bg-purple-100',
                    'completed'   => 'bg-green-100',
                ];
                $statusBadgeColors = [
                    'not_started' => 'bg-gray-500',
                    'in_progress' => 'bg-blue-500',
                    'in_review'   => 'bg-purple-500',
                    'completed'   => 'bg-green-500',
                ];
                $groupedTasks = $tasks->groupBy(function ($task) {
                    return $task->status ?? 'not_started';
                });
            @endphp

            {{-- Table View --}}
            <div id="table-view" class="{{ $isKanban ? 'hidden' : '' }} bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phase</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delay</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tasks as $task)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $task->task_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $task->phase->phase_name ?? 'Standalone' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $task->assignedTo->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ $task->progress_actual }}%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600">{{ $task->progress_actual }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $task->weight }}%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ ucfirst(str_replace('_', ' ', $task->status ?? 'Not Set')) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $effective = $effectiveDates[$task->id] ?? [];
                                        $planDelay = $effective['plan_delay_days'] ?? 0;
                                        $shift = $effective['dependency_shift_days'] ?? 0;
                                    @endphp
                                    @if($planDelay > 0)
                                        <span class="text-red-600 font-semibold" title="Plan delay">{{ $planDelay }}d delay</span>
                                    @endif
                                    @if($shift > 0)
                                        <span class="text-orange-600 font-semibold" title="Dependency shift">+{{ $shift }}d shift</span>
                                    @endif
                                    @if($planDelay <= 0 && $shift <= 0)
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.project.projects.tasks.edit', [$project, $task]) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                    <form action="{{ route('admin.project.projects.tasks.destroy', [$project, $task]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this task?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Kanban View --}}
            <div id="kanban-view" class="{{ $isKanban ? '' : 'hidden' }}">
                <div class="flex gap-4">
                    @foreach($statusColumns as $statusKey => $statusLabel)
                        @php
                            $columnTasks = $groupedTasks->get($statusKey, collect());
                        @endphp
                        <div class="bg-gray-50 rounded-lg border border-gray-200 flex flex-col flex-1 min-w-0">
                            <div class="px-3 py-2 border-b border-gray-200 flex items-center justify-between {{ $statusColors[$statusKey] }}">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $statusBadgeColors[$statusKey] }}"></span>
                                    <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide">{{ $statusLabel }}</h4>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200">{{ $columnTasks->count() }}</span>
                            </div>
                            <div class="p-3 space-y-3 flex-1">
                                @forelse($columnTasks as $task)
                                    <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm hover:shadow-md transition relative">
                                        <div class="flex items-start justify-between mb-2 gap-2">
                                            <span class="text-sm font-semibold text-gray-900 line-clamp-2">{{ $task->task_name }}</span>
                                            <div class="relative kanban-menu">
                                                <button type="button" class="kanban-menu-btn text-gray-400 hover:text-gray-600 focus:outline-none p-0.5 rounded hover:bg-gray-100">
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
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between text-xs text-gray-500">
                                                <span>{{ $task->phase->phase_name ?? 'Standalone' }}</span>
                                                <span class="font-medium text-gray-700">{{ $task->weight }}%</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                                    <div class="h-1.5 rounded-full" style="width: {{ $task->progress_actual }}%; background-color: #22c55e;"></div>
                                                </div>
                                                <span class="text-xs text-gray-600">{{ $task->progress_actual }}%</span>
                                            </div>
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">{{ $task->assignedTo->name ?? 'Unassigned' }}</span>
                                                @php
                                                    $effective = $effectiveDates[$task->id] ?? [];
                                                    $planDelay = $effective['plan_delay_days'] ?? 0;
                                                @endphp
                                                @if($planDelay > 0)
                                                    <span class="text-red-600 font-semibold">{{ $planDelay }}d delay</span>
                                                @endif
                                            </div>
                                            <div class="pt-2 border-t border-gray-100">
                                                <div class="flex items-center gap-4">
                                                    <button type="button"
                                                            class="comment-toggle-btn flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600 focus:outline-none"
                                                            data-task-id="{{ $task->id }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                        </svg>
                                                        <span>{{ $task->comments_count }}</span>
                                                    </button>
                                                    <button type="button"
                                                            class="attachment-toggle-btn flex items-center gap-1 text-xs text-gray-500 hover:text-indigo-600 focus:outline-none"
                                                            data-task-id="{{ $task->id }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                        </svg>
                                                        <span>{{ $task->attachments_count }}</span>
                                                    </button>
                                                </div>
                                                <div class="comment-section hidden mt-2 space-y-2" data-task-id="{{ $task->id }}">
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
                                                <div class="attachment-section hidden mt-2 space-y-2" data-task-id="{{ $task->id }}">
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
            });
        </script>
    @endpush
</x-app-layout>
