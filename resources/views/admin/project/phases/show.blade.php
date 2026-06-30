<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Phase Details — {{ $phase->phase_name }}</h2>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('admin.project.projects.phases.index', $project) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Phases
            </a>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Phase Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ $phase->phase_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Order</p>
                            <p class="text-sm text-gray-700">#{{ $phase->phase_order }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Progress</h4>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Plan Progress</span>
                                <span class="font-medium text-gray-900">{{ $phase->progress_plan }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $phase->progress_plan }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Actual Progress</span>
                                <span class="font-medium text-gray-900">{{ $phase->progress_actual }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $phase->progress_actual }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-100">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Timeline</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Planned</p>
                        <div class="space-y-1">
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Start:</span>
                                {{ $phase->start_date_plan ? $phase->start_date_plan->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $phase->end_date_plan ? $phase->end_date_plan->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Actual</p>
                        <div class="space-y-1">
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Start:</span>
                                {{ $phase->start_date_actual ? $phase->start_date_actual->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $phase->end_date_actual ? $phase->end_date_actual->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Revised</p>
                        <div class="space-y-1">
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Start:</span>
                                {{ $phase->start_date_revise ? $phase->start_date_revise->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $phase->end_date_revise ? $phase->end_date_revise->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tasks in this phase --}}
        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Tasks ({{ $phase->tasks->count() }})</h3>
                <a href="{{ route('admin.project.projects.tasks.create', $project) }}?phase_id={{ $phase->id }}"
                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    Add Task
                </a>
            </div>
            @if($phase->tasks->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-sm text-gray-500">No tasks in this phase yet.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($phase->tasks as $task)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $task->task_name }}</div>
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
                                        {{ ucfirst(str_replace('_', ' ', $task->status ?? 'Not Set')) }}
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
            @endif
        </div>
    </div>
</x-app-layout>
