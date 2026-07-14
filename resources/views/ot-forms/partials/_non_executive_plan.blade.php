{{-- Non-Executive OT Form — matches physical form layout --}}
<div class="overflow-x-auto">
    <table class="w-full border-collapse border text-[10px]">
        <thead>
            <tr>
                <th rowspan="2" class="border px-1 py-0.5 text-center w-8">TARIKH</th>
                <th rowspan="2" class="border px-1 py-0.5 text-left w-32">TUGAS ATAU AKTIVITI</th>
                <th colspan="3" class="border px-1 py-0.5 text-center bg-blue-50">MASA DIRANCANG</th>
                <th colspan="3" class="border px-1 py-0.5 text-center bg-green-50">MASA SEBENAR</th>
                <th rowspan="2" class="border px-1 py-0.5 text-center w-8">MAKAN<br>(>3 JAM)</th>
                <th rowspan="2" class="border px-1 py-0.5 text-center w-8">SHIFT</th>
                <th colspan="3" class="border px-1 py-0.5 text-center bg-yellow-50">KELULUSAN</th>
                <th colspan="4" class="border px-1 py-0.5 text-center bg-orange-50">JENIS OT</th>
                <th colspan="5" class="border px-1 py-0.5 text-center bg-purple-50">PENGIRAAN OT</th>
            </tr>
            <tr>
                <th class="border px-0.5 py-0.5 text-center bg-blue-50 w-12">MULA</th>
                <th class="border px-0.5 py-0.5 text-center bg-blue-50 w-12">TAMAT</th>
                <th class="border px-0.5 py-0.5 text-center bg-blue-50 w-10">JUMLAH</th>
                <th class="border px-0.5 py-0.5 text-center bg-green-50 w-12">MULA</th>
                <th class="border px-0.5 py-0.5 text-center bg-green-50 w-12">TAMAT</th>
                <th class="border px-0.5 py-0.5 text-center bg-green-50 w-10">JUMLAH</th>
                <th class="border px-0.5 py-0.5 text-center bg-yellow-50 w-10">KAKI-<br>TANGAN</th>
                <th class="border px-0.5 py-0.5 text-center bg-yellow-50 w-8">HOD</th>
                <th class="border px-0.5 py-0.5 text-center bg-yellow-50 w-10">DGM/<br>CEO/MD</th>
                <th class="border px-0.5 py-0.5 text-center bg-orange-50 w-8">NOR-<br>MAL</th>
                <th class="border px-0.5 py-0.5 text-center bg-orange-50 w-8">TRAIN</th>
                <th class="border px-0.5 py-0.5 text-center bg-orange-50 w-8">KAI-<br>ZEN</th>
                <th class="border px-0.5 py-0.5 text-center bg-orange-50 w-8">5S</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 1</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 2</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 3</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 4</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 5</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groupedEntries = $otForm->entries->groupBy(fn($e) => $e->entry_date->format('Y-m-d'));
            @endphp
            @foreach($groupedEntries as $dateStr => $dateEntries)
            @foreach($dateEntries as $entryIdx => $entry)
                @php
                    $dayOfWeek = $entry->entry_date->dayOfWeek;
                    $isWeekend = in_array($dayOfWeek, [0, 6]);
                    $isFilled = $entry->project_code_id || $entry->planned_start_time || $entry->actual_start_time;
                    // For entries with data, trust is_public_holiday from attendance (auto-fill).
                    // For blank entries, fall back to the public holidays table.
                    $isPublicHoliday = $isFilled
                        ? $entry->is_public_holiday
                        : isset($publicHolidays[$entry->entry_date->format('Y-m-d')]);
                    $isRestOrPH = $isWeekend || $isPublicHoliday;
                    $rowBg = $isPublicHoliday ? 'bg-red-50 hover:bg-red-100' : ($isWeekend ? 'bg-gray-50' : '');
                    $isFirstRow = $entryIdx === 0;
                    $isExtraRow = $entryIdx > 0;
                    $planTimeOptions = [];
                    for ($h = 0; $h <= 23; $h++) {
                        for ($m = 0; $m < 60; $m += 30) {
                            $planTimeOptions[] = sprintf('%02d:%02d', $h, $m);
                        }
                    }
                @endphp
                <tr class="entry-row {{ $rowBg }} {{ $isFilled ? 'has-data' : 'empty-row' }}"
                    data-entry-id="{{ $entry->id }}"
                    data-entry-date="{{ $entry->entry_date->format('Y-m-d') }}"
                    data-is-weekend="{{ $isWeekend ? '1' : '0' }}">
                    {{-- TARIKH --}}
                    <td class="border px-1 py-0.5 text-center {{ $isWeekend || $isPublicHoliday ? 'text-red-600' : '' }}">
                        @if($isFirstRow)
                            <div class="flex items-center justify-center gap-0.5">
                                <span>{{ $entry->entry_date->day }}</span>
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

                    {{-- TUGAS ATAU AKTIVITI --}}
                    <td class="border px-0.5 py-0.5">
                        @if($otForm->isEditable())
                            <x-project-code-selector
                                :entry-id="'nonexec-' . $entry->id"
                                :name-prefix="'entries[' . $entry->id . ']'"
                                :selected-project-code-id="$entry->project_code_id"
                                :selected-category="$entry->project_category"
                                :manual-project-name="$entry->manual_project_code_name"
                                :project-name="$entry->project_name"
                                input-class="w-full border-0 text-[10px] py-0 px-0 focus:ring-0"
                                :disabled="false"
                            />
                        @else
                            <span class="text-[10px]">
                                @if($entry->project_category)
                                    {{ $entry->project_category }}{{ $entry->manual_project_code_name ? ' - ' . $entry->manual_project_code_name : '' }}
                                @else
                                    {{ $entry->projectCode ? $entry->projectCode->code : '' }}
                                    {{ $entry->project_name ? $entry->project_name : '' }}
                                @endif
                            </span>
                        @endif
                    </td>

                    {{-- MASA DIRANCANG: MULA --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][planned_start_time]"
                                    onchange="calcTotal({{ $entry->id }}, 'planned')"
                                    class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                                <option value="">--</option>
                                @foreach($planTimeOptions as $t)
                                    <option value="{{ $t }}" {{ $entry->planned_start_time && substr($entry->planned_start_time, 0, 5) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        @else
                            {{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA DIRANCANG: TAMAT --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][planned_end_time]"
                                    onchange="calcTotal({{ $entry->id }}, 'planned')"
                                    class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                                <option value="">--</option>
                                @foreach($planTimeOptions as $t)
                                    <option value="{{ $t }}" {{ $entry->planned_end_time && substr($entry->planned_end_time, 0, 5) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        @else
                            {{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA DIRANCANG: JUMLAH --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="planned-total-{{ $entry->id }}"
                               name="entries[{{ $entry->id }}][planned_total_hours]"
                               value="{{ number_format($entry->planned_total_hours ?? 0, 2) }}"
                               class="plan-total w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>

                    {{-- MASA SEBENAR: MULA --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <div class="relative w-full pr-4">
                                <input type="time" name="entries[{{ $entry->id }}][actual_start_time]"
                                       value="{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}"
                                       onchange="calcTotal({{ $entry->id }}, 'actual'); calcOT({{ $entry->id }})"
                                       class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                                <button type="button" onclick="clearActualTime({{ $entry->id }}, 'start')" title="Clear actual start time"
                                        class="absolute top-1/2 -translate-y-1/2 right-0 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] leading-none flex items-center justify-center hover:bg-red-600 z-10 shadow-sm">×</button>
                            </div>
                        @else
                            {{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA SEBENAR: TAMAT --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <div class="relative w-full pr-4">
                                <input type="time" name="entries[{{ $entry->id }}][actual_end_time]"
                                       value="{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}"
                                       onchange="calcTotal({{ $entry->id }}, 'actual'); calcOT({{ $entry->id }})"
                                       class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                                <button type="button" onclick="clearActualTime({{ $entry->id }}, 'end')" title="Clear actual end time"
                                        class="absolute top-1/2 -translate-y-1/2 right-0 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] leading-none flex items-center justify-center hover:bg-red-600 z-10 shadow-sm">×</button>
                            </div>
                        @else
                            {{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA SEBENAR: JUMLAH --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="actual-total-{{ $entry->id }}"
                               name="entries[{{ $entry->id }}][actual_total_hours]"
                               value="{{ number_format($entry->actual_total_hours ?? 0, 2) }}"
                               class="actual-total w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>

                    {{-- MAKAN 0-3 JAM --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="checkbox" name="entries[{{ $entry->id }}][meal_break]" value="1"
                                   {{ $entry->meal_break ? 'checked' : '' }}
                                   class="rounded border-gray-300 h-3 w-3">
                        @else
                            {{ $entry->meal_break ? '/' : '' }}
                        @endif
                    </td>

                    {{-- SHIFT --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="checkbox" name="entries[{{ $entry->id }}][is_shift]" value="1"
                                   {{ $entry->is_shift ? 'checked' : '' }}
                                   onchange="calcOT({{ $entry->id }})"
                                   class="rounded border-gray-300 h-3 w-3">
                        @else
                            {{ $entry->is_shift ? '/' : '' }}
                        @endif
                    </td>

                    {{-- PUBLIC HOLIDAY (hidden for calculation) --}}
                    <td class="border px-0.5 py-0.5 text-center hidden">
                        @if($otForm->isEditable())
                            <input type="checkbox" name="entries[{{ $entry->id }}][is_public_holiday]" value="1"
                                   {{ $isPublicHoliday ? 'checked' : '' }}
                                   onchange="calcOT({{ $entry->id }})"
                                   id="ph-{{ $entry->id }}">
                        @endif
                    </td>

                    {{-- KELULUSAN: KAKITANGAN --}}
                    <td class="border px-0.5 py-0.5 text-center align-middle">
                        @if($isFilled && !in_array($otForm->status, ['draft']))
                            <div class="flex flex-col items-center leading-none">
                                <svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[5px] text-red-500 font-medium truncate max-w-[40px]">{{ $staffApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>
                    {{-- KELULUSAN: HOD --}}
                    <td class="border px-0.5 py-0.5 text-center align-middle">
                        @if($isFilled && in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved']))
                            <div class="flex flex-col items-center leading-none">
                                <svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[5px] text-red-500 font-medium truncate max-w-[40px]">{{ $managerApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>
                    {{-- KELULUSAN: DGM/CEO/MD --}}
                    <td class="border px-0.5 py-0.5 text-center align-middle">
                        @if($isFilled && $otForm->status === 'approved')
                            <div class="flex flex-col items-center leading-none">
                                <svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="14" text-anchor="middle" fill="currentColor" font-size="6" font-weight="bold" font-family="Arial">TSSB</text></svg>
                                <span class="text-[5px] text-red-500 font-medium truncate max-w-[40px]">{{ $gmApproverName ?? '' }}</span>
                            </div>
                        @endif
                    </td>

                    {{-- JENIS OT: NORMAL, TRAINING, KAIZEN, 5S --}}
                    @foreach([['jenis_ot_normal','normal'],['jenis_ot_training','training'],['jenis_ot_kaizen','kaizen'],['jenis_ot_5s','5s']] as [$field, $label])
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="checkbox" name="entries[{{ $entry->id }}][{{ $field }}]" value="1"
                                   {{ $entry->$field ? 'checked' : '' }}
                                   class="rounded border-gray-300 h-3 w-3">
                        @else
                            {{ $entry->$field ? '✓' : '' }}
                        @endif
                    </td>
                    @endforeach

                    {{-- PENGIRAAN OT: OT 1-5 --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="ot1-{{ $entry->id }}" name="entries[{{ $entry->id }}][ot_normal_day_hours]"
                               value="{{ number_format($entry->ot_normal_day_hours ?? 0, 2) }}"
                               class="ot-cell w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="ot2-{{ $entry->id }}" name="entries[{{ $entry->id }}][ot_rest_day_hours]"
                               value="{{ number_format($entry->ot_rest_day_hours ?? 0, 2) }}"
                               class="ot-cell w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="ot3-{{ $entry->id }}" name="entries[{{ $entry->id }}][ot_rest_day_excess_hours]"
                               value="{{ number_format($entry->ot_rest_day_excess_hours ?? 0, 2) }}"
                               class="ot-cell w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="ot4-{{ $entry->id }}" name="entries[{{ $entry->id }}][ot_ph_hours]"
                               value="{{ number_format($entry->ot_ph_hours ?? 0, 2) }}"
                               class="ot-cell w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>
                    <td class="border px-0.5 py-0.5 text-center">
                        <input type="text" id="ot5-{{ $entry->id }}" name="entries[{{ $entry->id }}][ot_rest_day_count]"
                               value="{{ $entry->ot_rest_day_count ?? 0 }}"
                               class="ot-cell w-full border-0 text-[10px] py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                    </td>
                </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
</div>
