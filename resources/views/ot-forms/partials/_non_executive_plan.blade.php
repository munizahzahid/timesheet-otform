{{-- Non-Executive OT Form — matches physical form layout --}}
<div class="overflow-x-auto">
    <table class="w-full border-collapse border text-[10px]">
        <thead>
            <tr>
                <th rowspan="2" class="border px-1 py-0.5 text-center w-8">TARIKH</th>
                <th rowspan="2" class="border px-1 py-0.5 text-left w-32">TUGAS ATAU AKTIVITI</th>
                <th colspan="3" class="border px-1 py-0.5 text-center bg-blue-50">MASA DIRANCANG</th>
                <th colspan="3" class="border px-1 py-0.5 text-center bg-green-50">MASA SEBENAR</th>
                <th rowspan="2" class="border px-1 py-0.5 text-center w-8">MAKAN<br>0-3 JAM</th>
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
                <th class="border px-0.5 py-0.5 text-center bg-orange-50 w-8">SS</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 1</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 2</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 3</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 4</th>
                <th class="border px-0.5 py-0.5 text-center bg-purple-50 w-8">OT 5</th>
            </tr>
        </thead>
        <tbody>
            @foreach($otForm->entries as $entry)
                @php
                    $dayOfWeek = $entry->entry_date->dayOfWeek;
                    $isWeekend = in_array($dayOfWeek, [0, 6]);
                    $rowBg = $isWeekend ? 'bg-gray-50' : '';
                    $isFilled = $entry->project_code_id || $entry->planned_start_time || $entry->actual_start_time;
                @endphp
                <tr class="entry-row {{ $rowBg }} {{ $isFilled ? 'has-data' : 'empty-row' }}"
                    data-entry-id="{{ $entry->id }}"
                    data-entry-date="{{ $entry->entry_date->format('Y-m-d') }}"
                    data-is-weekend="{{ $isWeekend ? '1' : '0' }}">
                    {{-- TARIKH --}}
                    <td class="border px-1 py-0.5 text-center {{ $isWeekend ? 'text-red-600' : '' }}">
                        {{ $entry->entry_date->day }}
                    </td>

                    {{-- TUGAS ATAU AKTIVITI --}}
                    <td class="border px-0.5 py-0.5">
                        @if($otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][project_code_id]"
                                    id="project-code-{{ $entry->id }}"
                                    onchange="updateProjectName({{ $entry->id }})"
                                    class="w-full border-0 text-[10px] py-0 px-0 focus:ring-0">
                                <option value="">--</option>
                                @foreach($projectCodes as $p)
                                    <option value="{{ $p->id }}" {{ $entry->project_code_id == $p->id ? 'selected' : '' }}
                                            data-name="{{ $p->name }}">
                                        {{ $p->code }}{{ $p->name ? ' - ' . $p->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="entries[{{ $entry->id }}][project_name]"
                                   id="project-name-{{ $entry->id }}" value="{{ $entry->project_name }}">
                        @else
                            {{ $entry->project_code ? $entry->project_code->code : '' }}
                            {{ $entry->project_name ? $entry->project_name : '' }}
                        @endif
                    </td>

                    {{-- MASA DIRANCANG: MULA --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][planned_start_time]"
                                   value="{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'planned')"
                                   class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                        @else
                            {{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA DIRANCANG: TAMAT --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][planned_end_time]"
                                   value="{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'planned')"
                                   class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
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
                            <input type="time" name="entries[{{ $entry->id }}][actual_start_time]"
                                   value="{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual'); calcOT({{ $entry->id }})"
                                   class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
                        @else
                            {{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}
                        @endif
                    </td>

                    {{-- MASA SEBENAR: TAMAT --}}
                    <td class="border px-0.5 py-0.5 text-center">
                        @if($otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][actual_end_time]"
                                   value="{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual'); calcOT({{ $entry->id }})"
                                   class="w-full border-0 text-[10px] py-0 px-0 text-center focus:ring-0">
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
                                   {{ $entry->is_public_holiday ? 'checked' : '' }}
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
                        @if($isFilled && in_array($otForm->status, ['pending_gm', 'approved']))
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
        </tbody>
    </table>
</div>
