@php
    $corrections = $entry->hr_corrections ?? [];
    $original = is_array($corrections) ? ($corrections[$field] ?? null) : null;
    $current = $entry->$field;
    $currentStr = $current ? substr($current, 0, 5) : '';
    $originalStr = $original ? substr($original, 0, 5) : '';
    $hasCorrection = $originalStr && $originalStr !== $currentStr;
@endphp
@if($hasCorrection)
    <div>{{ $currentStr }}</div>
    <div class="text-red-600 text-[10px]" style="text-decoration: line-through; text-decoration-color: #dc2626;">{{ $originalStr }}</div>
@else
    {{ $currentStr }}
@endif
