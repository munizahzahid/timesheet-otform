<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">List of Project</h2>
            <a href="{{ route('project.projects.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 hover:shadow-md transition shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Project
            </a>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @php
            $currentUrl = request()->fullUrl();
            $listProps = [
                'projects' => $projects->map(function ($project) use ($currentUrl) {
                    return [
                        'id' => $project->id,
                        'project_name' => $project->project_name,
                        'project_code' => $project->project_code,
                        'status' => $project->status,
                        'description' => $project->description,
                        'start_date_plan_formatted' => $project->start_date_plan?->format('d M Y'),
                        'end_date_plan_formatted' => $project->end_date_plan?->format('d M Y'),
                        'overall_actual_progress' => $project->overall_actual_progress,
                        'show_url' => route('project.projects.show', $project),
                        'edit_url' => route('project.projects.edit', $project) . '?' . http_build_query(['redirect' => $currentUrl]),
                    ];
                })->values()->toArray(),
                'success' => session('success'),
                'addUrl' => route('project.projects.create'),
            ];
        @endphp
        <script type="application/json" id="project-list-props">@json($listProps)</script>
        <div id="project-list"></div>
    </div>
</x-app-layout>
