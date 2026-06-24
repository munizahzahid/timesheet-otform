{{-- Executive OT Form — user-friendly table layout --}}
<div class="overflow-x-auto">
    <table class="w-full border-collapse text-xs">
        <thead>
            <tr>
                <th rowspan="2" class="border border-gray-300 px-2 py-2 text-center w-20 font-bold bg-blue-50 text-gray-700">DATE</th>
                <th rowspan="2" class="border border-gray-300 px-2 py-2 text-left min-w-[180px] font-bold bg-blue-50 text-gray-700">PARTICULARS</th>
                <th colspan="3" class="border border-gray-300 px-2 py-1.5 text-center bg-green-50 font-bold text-gray-700">PLAN</th>
                <th colspan="3" class="border border-gray-300 px-2 py-1.5 text-center bg-yellow-50 font-bold text-gray-700">APPROVAL BEFORE OT</th>
                <th colspan="3" class="border border-gray-300 px-2 py-1.5 text-center bg-orange-50 font-bold text-gray-700">ACTUAL</th>
                <th colspan="3" class="border border-gray-300 px-2 py-1.5 text-center bg-purple-50 font-bold text-gray-700">TOTAL HOURS</th>
            </tr>
            <tr>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-green-50 text-gray-600 w-16">Start</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-green-50 text-gray-600 w-16">End</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-green-50 text-gray-600 w-14">Total</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-yellow-50 text-gray-600 w-14">Exec.</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-yellow-50 text-gray-600 w-14">HOD</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-yellow-50 text-gray-600 w-16">DGM/<br>CEO</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-orange-50 text-gray-600 w-16">Start</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-orange-50 text-gray-600 w-16">End</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-orange-50 text-gray-600 w-14">Total</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-purple-50 text-gray-600 w-16">Normal<br>Day</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-purple-50 text-gray-600 w-16">Rest<br>Day</th>
                <th class="border border-gray-300 px-1 py-1.5 text-center bg-purple-50 text-gray-600 w-16">Public<br>Holiday</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groupedEntries = $otForm->entries->groupBy(fn($e) => $e->entry_date->format('Y-m-d'));
            @endphp
            @foreach($groupedEntries as $dateStr => $dateEntries)
            @foreach($dateEntries as $entryIdx => $entry)
                @php
                    $isFilled = $entry->project_code_id || $entry->planned_start_time || $entry->actual_start_time;
                    $dow = $entry->entry_date->dayOfWeek;
                    $isRestOrPH = in_array($dow, [0, 6]) || $entry->is_public_holiday;
                    $isFirstRow = $entryIdx === 0;
                    $planTimeOptions = [];
                    for ($h = 0; $h <= 23; $h++) {
                        for ($m = 0; $m < 60; $m += 30) {
                            $planTimeOptions[] = sprintf('%02d:%02d', $h, $m);
                        }
                    }
                @endphp
                <tr class="entry-row hover:bg-gray-50"
                    data-entry-id="{{ $entry->id }}" data-entry-date="{{ $dateStr }}" data-is-weekend="{{ $isRestOrPH ? '1' : '0' }}">
                    {{-- DATE --}}
                    <td class="border border-gray-200 px-2 py-1.5 text-center text-xs">
                        @if($isFirstRow)
                            <div class="flex items-center justify-center gap-0.5">
                                <span>{{ $entry->entry_date->format('j/n') }}</span>
                                @if($otForm->isEditable())
                                    <button type="button" onclick="addEntryRow('{{ $dateStr }}')" class="text-green-600 hover:text-green-800 ml-0.5" title="Add another entry for this day">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                @endif
                            </div>
                        @else
                            @if($otForm->isEditable())
                                <button type="button" onclick="deleteEntryRow({{ $entry->id }})" class="text-red-500 hover:text-red-700" title="Remove this entry">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        @endif
                    </td>

                    {{-- PARTICULARS --}}
                    <td class="border border-gray-200 px-1 py-0.5">
                        @if($otForm->isEditable())
                            <x-project-code-selector
                                :entry-id="'exec-' . $entry->id"
                                :name-prefix="'entries[' . $entry->id . ']'"
                                :selected-project-code-id="$entry->project_code_id"
                                :selected-category="$entry->project_category"
                                :manual-project-name="$entry->manual_project_code_name"
                                :project-name="$entry->project_name"
                                input-class="w-full border-0 text-xs py-1 px-1 focus:ring-0 bg-transparent"
                                :disabled="false"
                            />
                        @else
                            <span class="px-1 text-xs">
                                @if($entry->project_category)
                                    {{ $entry->project_category }}{{ $entry->manual_project_code_name ? ' - ' . $entry->manual_project_code_name : '' }}
                                @else
                                    {{ $entry->projectCode ? $entry->projectCode->code : '' }}
                                    {{ $entry->project_name ? $entry->project_name : '' }}
                                @endif
                            </span>
                        @endif
                    </td>

                    {{-- PLAN START --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][planned_start_time]"
                                    onchange="calcTotal({{ $entry->id }}, 'planned')"
                                    class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                                <option value="">--</option>
                                @foreach($planTimeOptions as $t)
                                    <option value="{{ $t }}" {{ $entry->planned_start_time && substr($entry->planned_start_time, 0, 5) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="text-xs">{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- PLAN END --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][planned_end_time]"
                                    onchange="calcTotal({{ $entry->id }}, 'planned')"
                                    class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                                <option value="">--</option>
                                @foreach($planTimeOptions as $t)
                                    <option value="{{ $t }}" {{ $entry->planned_end_time && substr($entry->planned_end_time, 0, 5) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="text-xs">{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- PLAN TOTAL --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center">
                        <input type="text" id="planned-total-{{ $entry->id }}"
                               name="entries[{{ $entry->id }}][planned_total_hours]"
                               value="{{ number_format(abs($entry->planned_total_hours ?? 0), 2) }}"
                               class="plan-total w-full border-0 text-xs py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>

                    {{-- APPROVAL BEFORE: EXEC --}}
                    <td class="border border-gray-200 px-1 py-1 text-center align-middle">
                        @if($isFilled && !in_array($otForm->status, ['draft']))
                            <div class="flex flex-col items-center gap-0.5">
                                <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[8px] text-red-500 font-medium truncate max-w-[60px]">{{ $staffApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>
                    {{-- APPROVAL BEFORE: HOD --}}
                    <td class="border border-gray-200 px-1 py-1 text-center align-middle">
                        @if($isFilled && in_array($otForm->status, ['pending_gm', 'approved']))
                            <div class="flex flex-col items-center gap-0.5">
                                <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[8px] text-red-500 font-medium truncate max-w-[60px]">{{ $managerApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>
                    {{-- APPROVAL BEFORE: DGM/CEO/MD --}}
                    <td class="border border-gray-200 px-1 py-1 text-center align-middle">
                        @if($isFilled && $otForm->status === 'approved')
                            <div class="flex flex-col items-center gap-0.5">
                                <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[8px] text-red-500 font-medium truncate max-w-[60px]">{{ $gmApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>

                    {{-- ACTUAL START --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][actual_start_time]"
                                   value="{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @else
                            <span class="text-xs">{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- ACTUAL END --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][actual_end_time]"
                                   value="{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @else
                            <span class="text-xs">{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- ACTUAL TOTAL --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center">
                        <input type="text" id="actual-total-{{ $entry->id }}"
                               name="entries[{{ $entry->id }}][actual_total_hours]"
                               value="{{ number_format(abs($entry->actual_total_hours ?? 0), 2) }}"
                               class="actual-total w-full border-0 text-xs py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>

                    {{-- TOTAL HOURS: NORMAL DAY, REST DAY, PUBLIC HOLIDAY --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-xs">
                        @if($entry->ot_normal_day_hours > 0)
                            {{ number_format($entry->ot_normal_day_hours, 2) }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-xs">
                        @if($entry->ot_rest_day_hours > 0)
                            {{ number_format($entry->ot_rest_day_hours, 2) }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-xs">
                        @if($entry->ot_ph_hours > 0)
                            {{ number_format($entry->ot_ph_hours, 2) }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
</div>
