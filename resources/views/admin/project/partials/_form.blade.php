@php $isEdit = isset($project); @endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Left Column --}}
    <div class="space-y-5">
        <div>
            <label for="project_name" class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
            <input type="text" name="project_name" id="project_name"
                   value="{{ old('project_name', $isEdit ? $project->project_name : '') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   required>
            @error('project_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="project_code" class="block text-sm font-medium text-gray-700 mb-1">Project Code</label>
            <input type="text" name="project_code" id="project_code"
                   value="{{ old('project_code', $isEdit ? $project->project_code : '') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   placeholder="e.g. PRJ-001">
            @error('project_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="3"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      placeholder="Brief description of the project">{{ old('description', $isEdit ? $project->description : '') }}</textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" id="status"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">— Select Status —</option>
                <option value="active" {{ old('status', $isEdit ? $project->status : '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="completed" {{ old('status', $isEdit ? $project->status : '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="delayed" {{ old('status', $isEdit ? $project->status : '') === 'delayed' ? 'selected' : '' }}>Delayed</option>
                <option value="on_hold" {{ old('status', $isEdit ? $project->status : '') === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                <option value="cancelled" {{ old('status', $isEdit ? $project->status : '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Right Column --}}
    <div class="space-y-5">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Planned Dates</h4>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date_plan" class="block text-xs font-medium text-gray-600 mb-1">Start Date</label>
                    <input type="date" name="start_date_plan" id="start_date_plan"
                           value="{{ old('start_date_plan', $isEdit && $project->start_date_plan ? $project->start_date_plan->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_plan" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_plan" id="end_date_plan"
                           value="{{ old('end_date_plan', $isEdit && $project->end_date_plan ? $project->end_date_plan->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Actual Dates</h4>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date_actual" class="block text-xs font-medium text-gray-600 mb-1">Start Date</label>
                    <input type="date" name="start_date_actual" id="start_date_actual"
                           value="{{ old('start_date_actual', $isEdit && $project->start_date_actual ? $project->start_date_actual->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_actual" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_actual" id="end_date_actual"
                           value="{{ old('end_date_actual', $isEdit && $project->end_date_actual ? $project->end_date_actual->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Revised Dates</h4>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date_revise" class="block text-xs font-medium text-gray-600 mb-1">Start Date</label>
                    <input type="date" name="start_date_revise" id="start_date_revise"
                           value="{{ old('start_date_revise', $isEdit && $project->start_date_revise ? $project->start_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_revise" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_revise" id="end_date_revise"
                           value="{{ old('end_date_revise', $isEdit && $project->end_date_revise ? $project->end_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Form Actions --}}
<div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
    <a href="{{ route('admin.project.projects.index') }}"
       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
        Cancel
    </a>
    <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
        {{ $isEdit ? 'Update Project' : 'Create Project' }}
    </button>
</div>
