@php $isEdit = isset($phase); @endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Left Column --}}
    <div class="space-y-5">
        <div>
            <label for="phase_name" class="block text-sm font-medium text-gray-700 mb-1">Phase Name <span class="text-red-500">*</span></label>
            <input type="text" name="phase_name" id="phase_name"
                   value="{{ old('phase_name', $isEdit ? $phase->phase_name : '') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   required>
            @error('phase_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phase_order" class="block text-sm font-medium text-gray-700 mb-1">Phase Order <span class="text-red-500">*</span></label>
            <input type="number" name="phase_order" id="phase_order" min="1"
                   value="{{ old('phase_order', $isEdit ? $phase->phase_order : 1) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   required>
            @error('phase_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
                           value="{{ old('start_date_plan', $isEdit && $phase->start_date_plan ? $phase->start_date_plan->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_plan" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_plan" id="end_date_plan"
                           value="{{ old('end_date_plan', $isEdit && $phase->end_date_plan ? $phase->end_date_plan->format('Y-m-d') : '') }}"
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
                           value="{{ old('start_date_actual', $isEdit && $phase->start_date_actual ? $phase->start_date_actual->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_actual" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_actual" id="end_date_actual"
                           value="{{ old('end_date_actual', $isEdit && $phase->end_date_actual ? $phase->end_date_actual->format('Y-m-d') : '') }}"
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
                           value="{{ old('start_date_revise', $isEdit && $phase->start_date_revise ? $phase->start_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_revise" class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date_revise" id="end_date_revise"
                           value="{{ old('end_date_revise', $isEdit && $phase->end_date_revise ? $phase->end_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Form Actions --}}
<div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
    <a href="{{ route('admin.project.projects.phases.index', $project) }}"
       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
        Cancel
    </a>
    <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
        {{ $isEdit ? 'Update Phase' : 'Create Phase' }}
    </button>
</div>
