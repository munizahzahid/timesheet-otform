@php
    $type = $type ?? 'text';
    $editMode = $editMode ?? false;
    $options = $options ?? [];
    $rows = $rows ?? 3;
    $inputValue = old($name, $value ?? '');
    if ($type === 'date' && $inputValue instanceof \Carbon\Carbon) {
        $inputValue = $inputValue->format('Y-m-d');
    }
    if ($type === 'number' && $inputValue !== '' && $inputValue !== null) {
        $inputValue = number_format((float) $inputValue, 2, '.', '');
    }
@endphp

<div>
    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-1">{{ $label }}</label>
    @if($editMode)
        @if($type === 'textarea')
            <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ $inputValue }}</textarea>
        @elseif($type === 'select')
            <select name="{{ $name }}" id="{{ $name }}"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" {{ (string) $inputValue === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
                   value="{{ $inputValue }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        @endif
        @error($name) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @else
        <p class="text-sm font-medium text-gray-900">{{ $value ?? '—' }}</p>
    @endif
</div>
