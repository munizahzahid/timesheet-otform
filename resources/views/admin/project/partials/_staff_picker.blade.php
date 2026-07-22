@php
    $editMode = $editMode ?? false;
    $inputValue = old($name, $value ?? '');
    $staffIdInputValue = old($staffIdName, $staffIdValue ?? '');
    $staffList = $staffList ?? collect();

    $selectedStaff = null;
    if ($staffIdInputValue) {
        $selectedStaff = $staffList->firstWhere('staff_no', $staffIdInputValue);
    }
    if (!$selectedStaff && $inputValue) {
        $selectedStaff = $staffList->firstWhere('name', $inputValue);
    }
    $hasCustomValue = $inputValue && (!$selectedStaff || $selectedStaff->name !== $inputValue);
@endphp

<div>
    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">{{ $label }}</label>
    @if($editMode)
        <select name="{{ $name }}" id="{{ $name }}"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm staff-picker"
                data-staff-id-field="{{ $staffIdName }}"
                data-department-field="{{ $departmentName }}">
            <option value="">— Select Staff —</option>
            @foreach($staffList as $staff)
                <option value="{{ $staff->name }}"
                        data-staff-id="{{ $staff->staff_no }}"
                        data-department="{{ $staff->department?->name }}"
                        {{ ($selectedStaff && $selectedStaff->id === $staff->id) ? 'selected' : '' }}>
                    {{ $staff->name }} ({{ $staff->staff_no }})
                </option>
            @endforeach
            @if($hasCustomValue)
                <option value="{{ $inputValue }}" selected>{{ $inputValue }}</option>
            @endif
        </select>
        @error($name) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @else
        <p class="text-sm font-medium text-gray-900">{{ $value ?? '—' }}</p>
    @endif
</div>
