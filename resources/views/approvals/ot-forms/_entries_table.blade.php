<table class="w-full border-collapse border text-xs" id="otEntriesTable">
    <thead>
        <tr>
            <th class="border px-2 py-1 text-center">{{ $otForm->isExecutive() ? 'DATE' : 'TARIKH' }}</th>
            <th class="border px-2 py-1 text-left">{{ $otForm->isExecutive() ? 'PARTICULARS' : 'TUGAS' }}</th>
            <th class="border px-2 py-1 text-center bg-blue-50">Plan Start</th>
            <th class="border px-2 py-1 text-center bg-blue-50">Plan End</th>
            <th class="border px-2 py-1 text-center bg-blue-50">Plan Total</th>
            <th class="border px-2 py-1 text-center bg-green-50">Actual Start</th>
            <th class="border px-2 py-1 text-center bg-green-50">Actual End</th>
            <th class="border px-2 py-1 text-center bg-green-50">Actual Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($otForm->entries as $entry)
            @php $isFilled = $entry->project_code_id || $entry->project_category || $entry->planned_start_time || $entry->actual_start_time; @endphp
            @if($isFilled)
            <tr data-entry-id="{{ $entry->id }}">
                <td class="border px-2 py-1 text-center">
                    {{ $otForm->isExecutive() ? $entry->entry_date->format('j/n/Y') : $entry->entry_date->day }}
                </td>
                <td class="border px-2 py-1">
                    @if($editMode)
                        <select name="entries[{{ $entry->id }}][project_code_id]" class="w-full border rounded px-1 py-0.5 text-xs entry-project-code">
                            <option value="">- Select -</option>
                            @foreach($projectCodes as $code)
                                <option value="{{ $code->id }}" {{ $entry->project_code_id == $code->id ? 'selected' : '' }}>{{ $code->code }} - {{ $code->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="entries[{{ $entry->id }}][project_name]" value="{{ $entry->project_name }}" class="w-full border rounded px-1 py-0.5 text-xs mt-1 entry-project-name" placeholder="Project name">
                    @else
                        @if($entry->project_category)
                            {{ $entry->project_category }}{{ $entry->manual_project_code_name ? ' - ' . $entry->manual_project_code_name : '' }}
                        @else
                            {{ $entry->projectCode ? $entry->projectCode->code : '' }}
                            {{ $entry->project_name ? $entry->project_name : '' }}
                        @endif
                    @endif
                </td>
                <td class="border px-2 py-1 text-center">
                    @if($editMode)
                        <input type="time" name="entries[{{ $entry->id }}][planned_start_time]" value="{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}" class="w-full border rounded px-1 py-0.5 text-xs entry-planned-start">
                    @else
                        {{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}
                    @endif
                </td>
                <td class="border px-2 py-1 text-center">
                    @if($editMode)
                        <input type="time" name="entries[{{ $entry->id }}][planned_end_time]" value="{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}" class="w-full border rounded px-1 py-0.5 text-xs entry-planned-end">
                    @else
                        {{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}
                    @endif
                </td>
                <td class="border px-2 py-1 text-center font-medium entry-planned-total">
                    {{ $entry->planned_total_hours > 0 ? number_format($entry->planned_total_hours, 2) : '' }}
                </td>
                <td class="border px-2 py-1 text-center">
                    @if($editMode)
                        <input type="time" name="entries[{{ $entry->id }}][actual_start_time]" value="{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}" class="w-full border rounded px-1 py-0.5 text-xs entry-actual-start">
                    @else
                        {{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}
                    @endif
                </td>
                <td class="border px-2 py-1 text-center">
                    @if($editMode)
                        <input type="time" name="entries[{{ $entry->id }}][actual_end_time]" value="{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}" class="w-full border rounded px-1 py-0.5 text-xs entry-actual-end">
                    @else
                        {{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}
                    @endif
                </td>
                <td class="border px-2 py-1 text-center font-medium entry-actual-total">
                    {{ $entry->actual_total_hours > 0 ? number_format($entry->actual_total_hours, 2) : '' }}
                </td>
            </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50">
        <tr>
            <td colspan="4" class="border px-2 py-1 text-right font-semibold">TOTAL:</td>
            <td class="border px-2 py-1 text-center font-bold" id="plannedTotal">{{ number_format($otForm->entries->sum('planned_total_hours'), 2) }}</td>
            <td colspan="2" class="border"></td>
            <td class="border px-2 py-1 text-center font-bold" id="actualTotal">{{ number_format($otForm->entries->sum('actual_total_hours'), 2) }}</td>
        </tr>
    </tfoot>
</table>
