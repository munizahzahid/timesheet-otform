<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">List of Project</h2>
            <a href="{{ route('admin.project.projects.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Project
            </a>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        @if($projects->count() > 0)
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($projects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <a href="{{ route('admin.project.projects.show', $project) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                        {{ $project->project_name }}
                                    </a>
                                    @if($project->description)
                                        <p class="text-xs text-gray-500 mt-0.5 truncate max-w-xs">{{ $project->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $project->project_code ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-blue-100 text-blue-800',
                                            'delayed' => 'bg-red-100 text-red-800',
                                            'on_hold' => 'bg-yellow-100 text-yellow-800',
                                            'cancelled' => 'bg-gray-100 text-gray-800',
                                        ];
                                        $color = $statusColors[$project->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                        {{ ucfirst(str_replace('_', ' ', $project->status ?? 'Not Set')) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($project->start_date_plan && $project->end_date_plan)
                                        {{ $project->start_date_plan->format('d M Y') }} — {{ $project->end_date_plan->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                            <div class="h-2.5 rounded-full" style="width: {{ $project->overall_actual_progress }}%; background-color: #22c55e;"></div>
                                        </div>
                                        <span class="text-xs text-gray-600">{{ $project->overall_actual_progress }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.project.projects.edit', $project) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                        Edit
                                    </a>
                                    <span class="text-gray-300 mx-1">|</span>
                                    <a href="{{ route('admin.project.projects.show', $project) }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-lg p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                <h3 class="mt-4 text-sm font-medium text-gray-900">No projects yet</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first project.</p>
                <div class="mt-6">
                    <a href="{{ route('admin.project.projects.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Project
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
