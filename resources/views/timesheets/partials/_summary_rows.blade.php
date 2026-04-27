{{-- TOTAL EXTERNAL PROJECT --}}
<tr class="bg-sky-100 font-bold">
    <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">TOTAL EXTERNAL PROJECT</td>
    <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
    @for($d = 1; $d <= $daysInMonth; $d++)
        <td class="border border-gray-300 px-1 py-1 text-center text-xs"
            x-text="totalExternalForDay({{ $d }})"></td>
    @endfor
    <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200"
        x-text="grandTotalExternal()"></td>
</tr>

{{-- TOTAL WORKING HOURS --}}
<tr class="bg-green-100 font-bold">
    <td class="sticky left-0 z-10 bg-green-100 border border-gray-300 px-2 py-1 text-[11px]">TOTAL WORKING HOURS</td>
    <td class="sticky left-[180px] z-10 bg-green-100 border border-gray-300 px-1 py-1"></td>
    @for($d = 1; $d <= $daysInMonth; $d++)
        <td class="border border-gray-300 px-1 py-1 text-center text-xs"
            x-text="totalWorkingForDay({{ $d }})"></td>
    @endfor
    <td class="border border-gray-300 px-1 py-1 text-center bg-green-200"
        x-text="grandTotalWorking()"></td>
</tr>

{{-- HOURS AVAILABLE --}}
<tr class="bg-gray-100 font-semibold">
    <td class="sticky left-0 z-10 bg-gray-100 border border-gray-300 px-2 py-1 text-[11px]">HOURS AVAILABLE</td>
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

{{-- OVERTIME --}}
<tr class="bg-sky-100 font-bold">
    <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">OVERTIME</td>
    <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
    @for($d = 1; $d <= $daysInMonth; $d++)
        <td class="border border-gray-300 px-1 py-1 text-center text-xs"
            x-text="overtimeForDay({{ $d }})"></td>
    @endfor
    <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200"
        x-text="grandTotalOvertime()"></td>
</tr>
