<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('View Timesheet') }} — {{ $timesheet->user->name }}
            </h2>
            <a href="{{ route('records.timesheets') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; {{ __('Back to list') }}</a>
        </div>
    </x-slot>

    <div class="max-w-full mx-auto">
        @include('timesheets.partials._header')

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="border-collapse text-xs" style="min-width: 100%;">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="sticky left-0 z-20 bg-gray-100 border border-gray-300 px-2 py-1 text-left min-w-[180px]">{{ __('ITEM') }}</th>
                            <th class="sticky left-[180px] z-20 bg-gray-100 border border-gray-300 px-1 py-1 min-w-[50px]">{{ __('TYPE') }}</th>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php $day = $days[$d]; @endphp
                                <th class="border border-gray-300 px-1 py-1 text-center min-w-[38px]
                                    {{ $day['day_type'] === 'off_day' ? 'bg-yellow-100' : '' }}
                                    {{ $day['day_type'] === 'public_holiday' ? 'bg-red-100' : '' }}
                                    {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-100' : '' }}">
                                    {{ $d }}
                                </th>
                            @endfor
                            <th class="border border-gray-300 px-2 py-1 text-center min-w-[50px] bg-gray-200 font-bold">{{ __('TOTAL') }}</th>
                        </tr>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 z-20 bg-gray-50 border border-gray-300 px-2 py-1"></th>
                            <th class="sticky left-[180px] z-20 bg-gray-50 border border-gray-300 px-1 py-1"></th>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php $day = $days[$d]; @endphp
                                <th class="border border-gray-300 px-1 py-0.5 text-center text-[10px] font-normal
                                    {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                    {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}">
                                    {{ $day['day_of_week'] }}
                                </th>
                            @endfor
                            <th class="border border-gray-300 px-2 py-1 bg-gray-200"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminTypes as $type => $label)
                            <tr class="{{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">
                                <td class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-1 font-medium text-[11px] whitespace-nowrap {{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">
                                    {{ $loop->iteration }}. {{ $label }}
                                </td>
                                <td class="sticky left-[180px] z-10 bg-white border border-gray-300 px-1 py-1 text-center text-[10px] text-gray-400 {{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">{{ __('hrs') }}</td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $day = $days[$d]; @endphp
                                    <td class="border border-gray-300 p-0 text-center
                                        {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                        {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                        {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                        <span class="text-xs">{{ $adminHours[$type][$d] ?? '' }}</span>
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50">
                                    @php
                                        $total = 0;
                                        foreach (($adminHours[$type] ?? []) as $h) {
                                            $total += is_numeric($h) ? (float)$h : 0;
                                        }
                                        echo $total > 0 ? number_format($total, 1) : '';
                                    @endphp
                                </td>
                            </tr>
                        @endforeach

                        <tr class="bg-gray-200 font-bold">
                            <td class="sticky left-0 z-10 bg-gray-200 border border-gray-300 px-2 py-1 text-[11px]">{{ __('TOTAL ADMIN JOB') }}</td>
                            <td class="sticky left-[180px] z-10 bg-gray-200 border border-gray-300 px-1 py-1"></td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                    @php
                                        $dayTotal = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            $dayTotal += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                        }
                                        echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                    @endphp
                                </td>
                            @endfor
                            <td class="border border-gray-300 px-1 py-1 text-center bg-gray-300">
                                @php
                                    $grandTotal = 0;
                                    foreach ($adminHours as $type => $hours) {
                                        foreach ($hours as $h) {
                                            $grandTotal += is_numeric($h) ? (float)$h : 0;
                                        }
                                    }
                                    echo number_format($grandTotal, 1);
                                @endphp
                            </td>
                        </tr>

                        <tr><td colspan="{{ $daysInMonth + 3 }}" class="h-2 bg-gray-100 border-0"></td></tr>

                        @foreach($flatProjectRows as $fRow)
                            <tr class="{{ $fRow['sIdx'] === 0 ? 'border-t-2 border-gray-400' : '' }}">
                                <td class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-1 align-top min-w-[180px] {{ $fRow['sIdx'] === 0 ? '' : 'border-t-0' }}">
                                    @if($fRow['sIdx'] === 0)
                                        <div>
                                            <div class="flex items-center gap-1 mb-1">
                                                <span class="font-bold text-[11px]">#{{ $fRow['pIdx'] + 1 }}</span>
                                            </div>
                                            <div class="text-[10px] font-medium">{{ $fRow['project_name'] }}</div>
                                            <div class="text-[9px] text-gray-400 mt-0.5 truncate">{{ $fRow['project_code'] }}</div>
                                        </div>
                                    @endif
                                </td>
                                <td class="sticky left-[180px] z-10 bg-white border border-gray-300 px-1 py-0.5 text-center text-[9px] font-medium whitespace-nowrap">
                                    {{ $fRow['label'] }}
                                </td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $day = $days[$d]; @endphp
                                    <td class="border border-gray-300 p-0 text-center
                                        {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                        {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                        {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                        <span class="text-xs">
                                            @php
                                                $val = $projectRowsData[$fRow['pIdx']]['hours'][$d][$fRow['field']] ?? 0;
                                                echo $val > 0 ? number_format($val, 1) : '';
                                            @endphp
                                        </span>
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50 text-xs">
                                    @php
                                        $total = 0;
                                        for ($d = 1; $d <= $daysInMonth; $d++) {
                                            $total += $projectRowsData[$fRow['pIdx']]['hours'][$d][$fRow['field']] ?? 0;
                                        }
                                        echo $total > 0 ? number_format($total, 1) : '';
                                    @endphp
                                </td>
                            </tr>
                        @endforeach

                        <tr><td colspan="{{ $daysInMonth + 3 }}" class="h-2 bg-gray-100 border-0"></td></tr>

                        <tr class="bg-sky-100 font-bold">
                            <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">{{ __('TOTAL EXTERNAL PROJECT') }}</td>
                            <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                    @php
                                        $dayTotal = 0;
                                        foreach ($projectRowsData as $project) {
                                            $dayTotal += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0);
                                        }
                                        echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                    @endphp
                                </td>
                            @endfor
                            <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200">
                                @php
                                    $grandTotal = 0;
                                    foreach ($projectRowsData as $project) {
                                        foreach ($project['hours'] as $dayHours) {
                                            $grandTotal += ($dayHours['normal_nc'] ?? 0) + ($dayHours['normal_cobq'] ?? 0);
                                        }
                                    }
                                    echo number_format($grandTotal, 1);
                                @endphp
                            </td>
                        </tr>

                        <tr class="bg-green-100 font-bold">
                            <td class="sticky left-0 z-10 bg-green-100 border border-gray-300 px-2 py-1 text-[11px]">{{ __('TOTAL WORKING HOURS') }}</td>
                            <td class="sticky left-[180px] z-10 bg-green-100 border border-gray-300 px-1 py-1"></td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                    @php
                                        $dayTotal = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            $dayTotal += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                        }
                                        foreach ($projectRowsData as $project) {
                                            $dayTotal += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0) + ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                                        }
                                        echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                    @endphp
                                </td>
                            @endfor
                            <td class="border border-gray-300 px-1 py-1 text-center bg-green-200">
                                @php
                                    $grandTotal = 0;
                                    foreach ($adminHours as $type => $hours) {
                                        foreach ($hours as $h) {
                                            $grandTotal += is_numeric($h) ? (float)$h : 0;
                                        }
                                    }
                                    foreach ($projectRowsData as $project) {
                                        foreach ($project['hours'] as $dayHours) {
                                            $grandTotal += ($dayHours['normal_nc'] ?? 0) + ($dayHours['normal_cobq'] ?? 0) + ($dayHours['ot_nc'] ?? 0) + ($dayHours['ot_cobq'] ?? 0);
                                        }
                                    }
                                    echo number_format($grandTotal, 1);
                                @endphp
                            </td>
                        </tr>

                        <tr class="bg-gray-100 font-semibold">
                            <td class="sticky left-0 z-10 bg-gray-100 border border-gray-300 px-2 py-1 text-[11px]">{{ __('HOURS AVAILABLE') }}</td>
                            <td class="sticky left-[180px] z-10 bg-gray-100 border border-gray-300 px-1 py-1"></td>
                            @php $totalAvail = 0; @endphp
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php $avail = $days[$d]['available_hours']; $totalAvail += $avail; @endphp
                                <td class="border border-gray-300 px-1 py-1 text-center text-xs
                                    {{ $days[$d]['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                    {{ $days[$d]['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                    {{ in_array($days[$d]['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                    {{ $avail > 0 ? $avail : '' }}
                                </td>
                            @endfor
                            <td class="border border-gray-300 px-1 py-1 text-center bg-gray-200">{{ $totalAvail }}</td>
                        </tr>

                        <tr class="bg-sky-100 font-bold">
                            <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">{{ __('OVERTIME') }}</td>
                            <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                    @php
                                        $dayWorking = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            $dayWorking += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                        }
                                        foreach ($projectRowsData as $project) {
                                            $dayWorking += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0)
                                                         + ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                                        }
                                        $dayOvertime = max(0, $dayWorking - $days[$d]['available_hours']);
                                        echo $dayOvertime > 0 ? number_format($dayOvertime, 1) : '';
                                    @endphp
                                </td>
                            @endfor
                            <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200">
                                @php
                                    $grandOvertime = 0;
                                    for ($d = 1; $d <= $daysInMonth; $d++) {
                                        $dayWorking = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            $dayWorking += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                        }
                                        foreach ($projectRowsData as $project) {
                                            $dayWorking += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0)
                                                         + ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                                        }
                                        $grandOvertime += max(0, $dayWorking - $days[$d]['available_hours']);
                                    }
                                    echo number_format($grandOvertime, 1);
                                @endphp
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($otApprovedByHr !== null)
        @php
            $rsOvertime = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayWorking = 0;
                foreach ($adminHours as $type => $hours) {
                    $dayWorking += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                }
                foreach ($projectRowsData as $project) {
                    $dayWorking += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0)
                                 + ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                }
                $rsOvertime += max(0, $dayWorking - $days[$d]['available_hours']);
            }
            $rsVariance = $otApprovedByHr - $rsOvertime;
        @endphp
        <div class="flex justify-end mb-4">
            <div class="border border-gray-300 bg-white w-64 text-sm">
                <div class="flex justify-between px-3 py-1 border-b border-gray-300 font-semibold">
                    <span>OT Approved by HR:</span>
                    <span>{{ number_format($otApprovedByHr, 2) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1 font-semibold {{ $rsVariance < 0 ? 'text-red-600' : '' }}">
                    <span>Variance:</span>
                    <span>{{ number_format($rsVariance, 2) }}</span>
                </div>
            </div>
        </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Export Options') }}</h3>
                <div class="flex items-center gap-4">
                    <a href="{{ route('timesheets.export-excel', $timesheet) }}"
                       class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                        {{ __('Download Excel') }}
                    </a>
                    <a href="{{ route('timesheets.export-pdf', $timesheet) }}"
                       class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                        {{ __('Download PDF') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Approval Trail') }}</h3>
                <x-approval-stamps :stamps="$approvalStamps" />
            </div>
        </div>
    </div>
</x-app-layout>
