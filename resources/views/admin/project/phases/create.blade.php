<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Phase — {{ $project->project_name }}</h2>
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

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <form method="POST" action="{{ route('admin.project.projects.phases.store', $project) }}">
                @csrf
                @include('admin.project.phases.partials._form')
            </form>
        </div>
    </div>
</x-app-layout>
