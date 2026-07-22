@php $isEdit = isset($task); @endphp

{{-- Task Name --}}
<div class="mb-5">
    <label for="task_name" class="block text-sm font-medium text-gray-700 mb-1">Task Name <span class="text-red-500">*</span></label>
    <input type="text" name="task_name" id="task_name"
           value="{{ old('task_name', $isEdit ? $task->task_name : '') }}"
           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
           required>
    @error('task_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Basic Info --}}
<div class="bg-gray-50 rounded-lg border border-gray-200 p-5 mb-5">
    <h4 class="text-sm font-semibold text-gray-700 mb-4">Basic Information</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <label for="task_order" class="block text-xs font-medium text-gray-600 mb-1">Task Order <span class="text-red-500">*</span></label>
            <input type="number" name="task_order" id="task_order" min="1"
                   value="{{ old('task_order', $isEdit ? $task->task_order : 1) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   required>
            @error('task_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phase_id" class="block text-xs font-medium text-gray-600 mb-1">Phase</label>
            <select name="phase_id" id="phase_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">— No Phase (Standalone) —</option>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}" {{ old('phase_id', $isEdit ? $task->phase_id : ($defaultPhaseId ?? '')) == $phase->id ? 'selected' : '' }}>
                        {{ $phase->phase_name }} (Order #{{ $phase->phase_order }})
                    </option>
                @endforeach
            </select>
            @error('phase_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="assigned_to" class="block text-xs font-medium text-gray-600 mb-1">Assigned To</label>
            <select name="assigned_to" id="assigned_to"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">— Unassigned —</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('assigned_to', $isEdit ? $task->assigned_to : '') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            @error('assigned_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="predecessor_task_id" class="block text-xs font-medium text-gray-600 mb-1">Predecessor Task (dependency)</label>
            <select name="predecessor_task_id" id="predecessor_task_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">— No Predecessor —</option>
                @foreach($tasks as $t)
                    @if(!$isEdit || $t->id != $task->id)
                        <option value="{{ $t->id }}" {{ old('predecessor_task_id', $isEdit ? $task->predecessor_task_id : '') == $t->id ? 'selected' : '' }}>
                            {{ $t->task_name }}
                        </option>
                    @endif
                @endforeach
            </select>
            @error('predecessor_task_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" id="status"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">— Select Status —</option>
                <option value="not_started" {{ old('status', $isEdit ? $task->status : '') === 'not_started' ? 'selected' : '' }}>Not Started</option>
                <option value="in_progress" {{ old('status', $isEdit ? $task->status : '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="completed" {{ old('status', $isEdit ? $task->status : '') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="on_hold" {{ old('status', $isEdit ? $task->status : '') === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                <option value="cancelled" {{ old('status', $isEdit ? $task->status : '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="weight" class="block text-xs font-medium text-gray-600 mb-1">Weight (%) <span class="text-red-500">*</span></label>
            <input type="number" name="weight" id="weight" min="0" max="100" required
                   value="{{ old('weight', $isEdit ? $task->weight : '') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            @error('weight') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- Dates --}}
<div class="bg-gray-50 rounded-lg border border-gray-200 p-5 mb-5">
    <h4 class="text-sm font-semibold text-gray-700 mb-4">Dates</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <label class="block text-xs font-medium text-indigo-700 mb-2">Planned Dates</label>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="start_date_plan" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">Start</label>
                    <input type="date" name="start_date_plan" id="start_date_plan"
                           value="{{ old('start_date_plan', $isEdit && $task->start_date_plan ? $task->start_date_plan->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_plan" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">End</label>
                    <input type="date" name="end_date_plan" id="end_date_plan"
                           value="{{ old('end_date_plan', $isEdit && $task->end_date_plan ? $task->end_date_plan->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-green-700 mb-2">Actual Dates</label>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="start_date_actual" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">Start</label>
                    <input type="date" name="start_date_actual" id="start_date_actual"
                           value="{{ old('start_date_actual', $isEdit && $task->start_date_actual ? $task->start_date_actual->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_actual" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">End</label>
                    <input type="date" name="end_date_actual" id="end_date_actual"
                           value="{{ old('end_date_actual', $isEdit && $task->end_date_actual ? $task->end_date_actual->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-orange-700 mb-2">Revised Dates</label>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="start_date_revise" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">Start</label>
                    <input type="date" name="start_date_revise" id="start_date_revise"
                           value="{{ old('start_date_revise', $isEdit && $task->start_date_revise ? $task->start_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="end_date_revise" class="block text-[10px] font-medium text-gray-500 mb-1 uppercase">End</label>
                    <input type="date" name="end_date_revise" id="end_date_revise"
                           value="{{ old('end_date_revise', $isEdit && $task->end_date_revise ? $task->end_date_revise->format('Y-m-d') : '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Progress --}}
<div class="bg-gray-50 rounded-lg border border-gray-200 p-5 mb-5">
    <h4 class="text-sm font-semibold text-gray-700 mb-4">Progress</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <label for="progress_actual" class="block text-xs font-medium text-gray-600 mb-1">Actual Progress (%)</label>
            <input type="number" name="progress_actual" id="progress_actual" min="0" max="100"
                   value="{{ old('progress_actual', $isEdit ? $task->progress_actual : 0) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
        <div>
            <label for="progress_plan" class="block text-xs font-medium text-gray-600 mb-1">Plan Progress (%)</label>
            <input type="number" name="progress_plan" id="progress_plan" min="0" max="100"
                   value="{{ old('progress_plan', $isEdit ? $task->progress_plan : 0) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
        <div>
            <label for="progress_revise" class="block text-xs font-medium text-gray-600 mb-1">Revise Progress (%)</label>
            <input type="number" name="progress_revise" id="progress_revise" min="0" max="100"
                   value="{{ old('progress_revise', $isEdit ? $task->progress_revise : '') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
    </div>
</div>

{{-- Remarks --}}
<div class="bg-gray-50 rounded-lg border border-gray-200 p-5 mb-5">
    <label for="remarks" class="block text-sm font-semibold text-gray-700 mb-3">Remarks</label>
    <textarea name="remarks" id="remarks" rows="3"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              placeholder="Additional notes or comments">{{ old('remarks', $isEdit ? $task->remarks : '') }}</textarea>
    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Form Actions --}}
<div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
    <a href="{{ route('admin.project.projects.show', $project) }}?tab=tasks"
       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
        Cancel
    </a>
    <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
        {{ $isEdit ? 'Update Task' : 'Create Task' }}
    </button>
</div>
