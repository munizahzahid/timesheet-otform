<table class="w-full border-collapse border text-xs">
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
        @php $originalPlannedTotal = 0; $originalActualTotal = 0; @endphp
        @foreach($otForm->entries as $entry)
            @php
                $corrections = $entry->hr_corrections ?? [];
                $hasOriginal = !empty($corrections);
                $isFilled = $hasOriginal || $entry->project_code_id || $entry->project_category || $entry->planned_start_time || $entry->actual_start_time;
            @endphp
            @if($isFilled)
            <tr>
                <td class="border px-2 py-1 text-center">
                    {{ $otForm->isExecutive() ? $entry->entry_date->format('j/n/Y') : $entry->entry_date->day }}
                </td>
                <td class="border px-2 py-1">
                    @if(($corrections['project_category'] ?? null) || $entry->project_category)
                        {{ $corrections['project_category'] ?? $entry->project_category }}{{ ($corrections['manual_project_code_name'] ?? $entry->manual_project_code_name) ? ' - ' . ($corrections['manual_project_code_name'] ?? $entry->manual_project_code_name) : '' }}
                    @else
                        @php $codeId = $corrections['project_code_id'] ?? $entry->project_code_id; @endphp
                        {{ $codeId ? ($projectCodes->firstWhere('id', $codeId)?->code ?? '') : '' }}
                        {{ $corrections['project_name'] ?? $entry->project_name }}
                    @endif
                </td>
                <td class="border px-2 py-1 text-center">{{ array_key_exists('planned_start_time', $corrections) ? ($corrections['planned_start_time'] ? substr($corrections['planned_start_time'], 0, 5) : '') : ($entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '') }}</td>
                <td class="border px-2 py-1 text-center">{{ array_key_exists('planned_end_time', $corrections) ? ($corrections['planned_end_time'] ? substr($corrections['planned_end_time'], 0, 5) : '') : ($entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '') }}</td>
                <td class="border px-2 py-1 text-center font-medium">
                    @php
                        $pStart = $corrections['planned_start_time'] ?? $entry->planned_start_time;
                        $pEnd = $corrections['planned_end_time'] ?? $entry->planned_end_time;
                        $pTotal = 0;
                        if ($pStart && $pEnd) {
                            $s = \Carbon\Carbon::parse($pStart);
                            $e = \Carbon\Carbon::parse($pEnd);
                            if ($e->lte($s)) $e->addDay();
                            $pTotal = max(0, round($e->diffInMinutes($s, true) / 60, 2));
                        }
                        $originalPlannedTotal += $pTotal;
                    @endphp
                    {{ $pTotal > 0 ? number_format($pTotal, 2) : '' }}
                </td>
                <td class="border px-2 py-1 text-center">{{ array_key_exists('actual_start_time', $corrections) ? ($corrections['actual_start_time'] ? substr($corrections['actual_start_time'], 0, 5) : '') : ($entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '') }}</td>
                <td class="border px-2 py-1 text-center">{{ array_key_exists('actual_end_time', $corrections) ? ($corrections['actual_end_time'] ? substr($corrections['actual_end_time'], 0, 5) : '') : ($entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '') }}</td>
                <td class="border px-2 py-1 text-center font-medium">
                    @php
                        $aStart = $corrections['actual_start_time'] ?? $entry->actual_start_time;
                        $aEnd = $corrections['actual_end_time'] ?? $entry->actual_end_time;
                        $aTotal = 0;
                        if ($aStart && $aEnd) {
                            $s = \Carbon\Carbon::parse($aStart);
                            $e = \Carbon\Carbon::parse($aEnd);
                            if ($e->lte($s)) $e->addDay();
                            $aTotal = max(0, round($e->diffInMinutes($s, true) / 60, 2));
                        }
                        $originalActualTotal += $aTotal;
                    @endphp
                    {{ $aTotal > 0 ? number_format($aTotal, 2) : '' }}
                </td>
            </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50">
        <tr>
            <td colspan="4" class="border px-2 py-1 text-right font-semibold">TOTAL:</td>
            <td class="border px-2 py-1 text-center font-bold">{{ number_format($originalPlannedTotal, 2) }}</td>
            <td colspan="2" class="border"></td>
            <td class="border px-2 py-1 text-center font-bold">{{ number_format($originalActualTotal, 2) }}</td>
        </tr>
    </tfoot>
</table>
