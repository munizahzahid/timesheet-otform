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
                $entries = $otForm->entries->all();
                $minRows = 20;
                $totalRows = max(count($entries), $minRows);
            @endphp
            @for($i = 0; $i < $totalRows; $i++)
                @php
                    $entry = $entries[$i] ?? null;
                    $isFilled = $entry && ($entry->project_code_id || $entry->planned_start_time || $entry->actual_start_time);
                @endphp
                <tr class="entry-row hover:bg-gray-50 {{ $i % 2 === 0 ? 'bg-white' : 'bg-gray-50/30' }}">
                    {{-- DATE --}}
                    <td class="border border-gray-200 px-2 py-1.5 text-center text-xs">
                        {{ $entry ? $entry->entry_date->format('j/n') : '' }}
                    </td>

                    {{-- PARTICULARS --}}
                    <td class="border border-gray-200 px-1 py-0.5">
                        @if($entry && $otForm->isEditable())
                            <select name="entries[{{ $entry->id }}][project_code_id]"
                                    id="project-code-{{ $entry->id }}"
                                    onchange="updateProjectName({{ $entry->id }})"
                                    class="w-full border-0 text-xs py-1 px-1 focus:ring-0 bg-transparent">
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
                        @elseif($entry)
                            <span class="px-1 text-xs">{{ $entry->project_code ? $entry->project_code->code : '' }}
                            {{ $entry->project_name ? $entry->project_name : '' }}</span>
                        @endif
                    </td>

                    {{-- PLAN START --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($entry && $otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][planned_start_time]"
                                   value="{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'planned')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @elseif($entry)
                            <span class="text-xs">{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- PLAN END --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($entry && $otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][planned_end_time]"
                                   value="{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'planned')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @elseif($entry)
                            <span class="text-xs">{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- PLAN TOTAL --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center">
                        @if($entry)
                            <input type="text" id="planned-total-{{ $entry->id }}"
                                   name="entries[{{ $entry->id }}][planned_total_hours]"
                                   value="{{ number_format($entry->planned_total_hours ?? 0, 2) }}"
                                   class="plan-total w-full border-0 text-xs py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                        @endif
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
                        @if($entry && $otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][actual_start_time]"
                                   value="{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @elseif($entry)
                            <span class="text-xs">{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- ACTUAL END --}}
                    <td class="border border-gray-200 px-0.5 py-0.5 text-center">
                        @if($entry && $otForm->isEditable())
                            <input type="time" name="entries[{{ $entry->id }}][actual_end_time]"
                                   value="{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}"
                                   onchange="calcTotal({{ $entry->id }}, 'actual')"
                                   class="w-full border-0 text-xs py-1 px-0.5 text-center focus:ring-0 bg-transparent">
                        @elseif($entry)
                            <span class="text-xs">{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}</span>
                        @endif
                    </td>

                    {{-- ACTUAL TOTAL --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center">
                        @if($entry)
                            <input type="text" id="actual-total-{{ $entry->id }}"
                                   name="entries[{{ $entry->id }}][actual_total_hours]"
                                   value="{{ number_format($entry->actual_total_hours ?? 0, 2) }}"
                                   class="actual-total w-full border-0 text-xs py-0 px-0 text-center bg-transparent focus:ring-0" readonly>
                        @endif
                    </td>

                    {{-- TOTAL HOURS: NORMAL DAY, REST DAY, PUBLIC HOLIDAY --}}
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-gray-400 text-xs">-</td>
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-gray-400 text-xs">-</td>
                    <td class="border border-gray-200 px-1 py-1.5 text-center text-gray-400 text-xs">-</td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>
