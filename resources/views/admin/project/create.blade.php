<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add New Project</h2>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <form method="POST" action="{{ route('admin.project.projects.store') }}">
                @csrf
                @include('admin.project.partials._form')
            </form>
        </div>
    </div>
</x-app-layout>
