<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-base font-bold text-indigo-600">
                {{ substr($user->name, 0, 1) }}
            </div>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500">All assigned tasks across all projects</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="javascript:history.back()" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phase</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($tasks as $task)
                                @php
                                    $statusColors = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'in_progress' => 'bg-blue-100 text-blue-800',
                                        'on_hold' => 'bg-yellow-100 text-yellow-800',
                                        'not_started' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                    ];
                                    $statusLabel = ucfirst(str_replace('_', ' ', $task->status ?? 'not_started'));
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('admin.project.projects.tasks.show', [$task->project, $task]) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $task->task_name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('admin.project.projects.show', $task->project) }}?tab=tasks" class="text-sm text-gray-600 hover:text-indigo-600">
                                            {{ $task->project->project_name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $task->phase->phase_name ?? 'Standalone' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$task->status ?? 'not_started'] ?? $statusColors['not_started'] }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $task->end_date_plan ? \Carbon\Carbon::parse($task->end_date_plan)->format('M d, Y') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-1.5 rounded-full bg-green-500" style="width: {{ $task->progress_actual ?? 0 }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-600">{{ $task->progress_actual ?? 0 }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $task->weight ?? 0 }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">
                                        No tasks assigned to this user.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
