<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Task Details — {{ $task->task_name }}</h2>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('admin.project.projects.show', $project) }}?tab=tasks" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Tasks
            </a>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Task Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ $task->task_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Order</p>
                            <p class="text-sm text-gray-700">#{{ $task->task_order }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Phase</p>
                            <p class="text-sm text-gray-700">{{ $task->phase->phase_name ?? 'Standalone' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Predecessor Task</p>
                            <p class="text-sm text-gray-700">{{ $task->predecessorTask->task_name ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Assigned To</p>
                            <p class="text-sm text-gray-700">{{ $task->assignedTo->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Status</p>
                            <p class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $task->status ?? 'Not Set')) }}</p>
                        </div>
                    </div>

                    @if($task->remarks)
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Remarks</p>
                            <p class="text-sm text-gray-700">{{ $task->remarks }}</p>
                        </div>
                    @endif
                </div>

                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Progress & Weight</h4>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Weight</span>
                                <span class="font-medium text-gray-900">{{ $task->weight }}%</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Plan Progress</span>
                                <span class="font-medium text-gray-900">{{ $task->progress_plan }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $task->progress_plan }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Actual Progress</span>
                                <span class="font-medium text-gray-900">{{ $task->progress_actual }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $task->progress_actual }}%"></div>
                            </div>
                        </div>
                        @if($task->progress_revise !== null)
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-600">Revise Progress</span>
                                    <span class="font-medium text-gray-900">{{ $task->progress_revise }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-orange-500 h-2 rounded-full" style="width: {{ $task->progress_revise }}%"></div>
                                </div>
                            </div>
                        @endif
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
                                {{ $task->start_date_plan ? $task->start_date_plan->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $task->end_date_plan ? $task->end_date_plan->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Actual</p>
                        <div class="space-y-1">
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Start:</span>
                                {{ $task->start_date_actual ? $task->start_date_actual->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $task->end_date_actual ? $task->end_date_actual->format('d M Y') : '—' }}
                            </p>
                            @php
                                $planDelay = $effective['plan_delay_days'] ?? 0;
                                $shift = $effective['dependency_shift_days'] ?? 0;
                            @endphp
                            @if($planDelay > 0)
                                <p class="text-sm text-red-600 font-semibold mt-2">
                                    {{ $planDelay }} day(s) delay vs plan end
                                </p>
                            @endif
                            @if($shift > 0)
                                <p class="text-sm text-orange-600 font-semibold mt-2">
                                    +{{ $shift }} day(s) dependency shift
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Revised</p>
                        <div class="space-y-1">
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Start:</span>
                                {{ $task->start_date_revise ? $task->start_date_revise->format('d M Y') : '—' }}
                            </p>
                            <p class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">End:</span>
                                {{ $task->end_date_revise ? $task->end_date_revise->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
