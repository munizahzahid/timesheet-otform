@php
    $tab = request('tab', 'dashboard');
    $editMode = request('edit', '0') === '1' && $tab === 'details';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $editMode ? 'Edit Project' : 'Project Details' }} — {{ $project->project_name }}
            </h2>
            <div class="flex items-center gap-2">
                @if($editMode)
                    <a href="{{ route('admin.project.projects.show', ['project' => $project, 'tab' => 'details']) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                @else
                    <a href="{{ route('admin.project.projects.edit', $project) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        Edit Project
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @push('top-left-actions')
        <a href="{{ route('admin.project.projects.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to List
        </a>
    @endpush

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @include('admin.project.partials._tertiary_navbar', ['tab' => $tab])

        @if($editMode)
            <form method="POST" action="{{ route('admin.project.projects.update', $project) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="details">

                @include('admin.project.partials._tab_details', ['editMode' => true, 'staffList' => $staffList])

                <div class="flex items-center justify-end gap-3 mt-6 bg-white border border-gray-200 rounded-lg p-4">
                    <a href="{{ route('admin.project.projects.show', $project) }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" name="action" value="save"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        Save Changes
                    </button>
                    <button type="submit" name="action" value="push_to_desknet"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        Save & Push to Desknet
                    </button>
                </div>
            </form>
        @elseif($tab === 'schedule')
            @include('admin.project.partials._tab_schedule')
        @elseif($tab === 'tasks')
            @include('admin.project.partials._tab_tasks')
        @elseif($tab === 'cards')
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
                <p class="text-sm text-gray-500">Cards view coming soon.</p>
            </div>
        @elseif($tab === 'details')
            @include('admin.project.partials._tab_details', ['editMode' => false, 'staffList' => $staffList])
        @else
            @include('admin.project.partials._tab_dashboard')
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.staff-picker').forEach(function (select) {
                    function updateFields() {
                        const option = select.options[select.selectedIndex];
                        const staffIdField = document.getElementById(select.dataset.staffIdField);
                        const departmentField = document.getElementById(select.dataset.departmentField);

                        // Only auto-fill when a staff list option is selected (has data attributes).
                        // Keep custom/manual values untouched for non-matching entries.
                        if (option && option.dataset.staffId) {
                            if (staffIdField) {
                                staffIdField.value = option.dataset.staffId;
                            }
                            if (departmentField) {
                                departmentField.value = option.dataset.department || '';
                            }
                        }
                    }

                    select.addEventListener('change', updateFields);
                    updateFields();
                });
            });
        </script>
    @endpush
</x-app-layout>
