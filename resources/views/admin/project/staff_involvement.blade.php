<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Staff Involvement</h2>
        </div>
    </x-slot>

    @push('top-left-actions')
        <a href="{{ route('admin.project.dashboard') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back</a>
    @endpush

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-lg font-medium text-gray-900">{{ $user->name }}</h3>
            <p class="text-sm text-gray-500">{{ $user->staff_no ?? 'No staff number' }}</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden" x-data="{ openProject: null }">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Projects</h3>
                <p class="text-xs text-gray-400">Click a project to view assigned tasks</p>
            </div>

            @forelse($projectData as $data)
                <div class="border-b border-gray-100 last:border-0">
                    <button
                        type="button"
                        class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition text-left"
                        @click="openProject = openProject === {{ $data['project']->id }} ? null : {{ $data['project']->id }}"
                    >
                        <div class="min-w-0">
                            <h4 class="text-sm font-medium text-gray-900 truncate">{{ $data['project']->project_name }}</h4>
                            <div class="flex flex-wrap gap-2 mt-1.5">
                                @foreach($data['roles'] as $role)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium
                                        {{ $role === 'Project Manager' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $role === 'Deskman 1' || $role === 'Deskman 2' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $role === 'Task Assigned' ? 'bg-green-100 text-green-800' : '' }}
                                    ">{{ $role }}</span>
                                @endforeach
                            </div>
                        </div>
                        <svg
                            class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform"
                            :class="{ 'rotate-180': openProject === {{ $data['project']->id }} }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        x-show="openProject === {{ $data['project']->id }}"
                        x-transition
                        class="bg-gray-50 px-5 py-3"
                    >
                        @if($data['tasks']->isNotEmpty())
                            <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Assigned Tasks</h5>
                            <ul class="space-y-2">
                                @foreach($data['tasks'] as $task)
                                    <li class="bg-white border border-gray-200 rounded p-3">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-gray-900">{{ $task->task_name }}</p>
                                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-800">{{ $task->status ?? 'No status' }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">
                                            @if($task->start_date_plan && $task->end_date_plan)
                                                {{ $task->start_date_plan->format('d M Y') }} - {{ $task->end_date_plan->format('d M Y') }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">No tasks assigned for this project.</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm text-gray-500 mt-2">No project involvement found for this staff.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
