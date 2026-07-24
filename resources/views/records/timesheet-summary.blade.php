<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Timesheet Summary') }}</h2>
    </x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    @php
        $staffIds = $staff->pluck('id')->toArray();
        $rowHourTypes = [
            ['key' => 'normal_nc', 'group' => 'NORMAL', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'normal_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
            ['key' => 'ot_nc', 'group' => 'OT', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'ot_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
        ];

        $prefixes = ['MUHAMMAD ', 'MOHAMMAD ', 'MOHAMMED ', 'MUHAMAD ', 'MOHAMED ', 'MOHAMAD ', 'MOHD ', 'MUHD ', 'NURUL ', 'NUR ', 'SITI '];
        $separators = [' BIN ', ' BINTI ', ' B ', ' BT '];
        $displayStaff = [];
        foreach ($staff as $user) {
            $name = strtoupper($user->name);
            foreach ($prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $name = substr($name, strlen($prefix));
                    break;
                }
            }
            foreach ($separators as $sep) {
                $pos = strpos($name, $sep);
                if ($pos !== false) {
                    $name = substr($name, 0, $pos);
                    break;
                }
            }
            $displayStaff[] = ['id' => $user->id, 'name' => trim($name)];
        }
        while (count($displayStaff) < 13) {
            $displayStaff[] = ['id' => null, 'name' => ''];
        }
        $staffCount = count($displayStaff);
        $staffCellClass = 'border border-gray-300 px-2 py-1 text-center min-w-[80px] max-w-[80px] whitespace-normal break-words';
    @endphp

    <div class="max-w-full mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                {{-- Filters --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('records.timesheets') }}" class="px-3 py-1.5 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">{{ __('Timesheet') }}</a>
                        <span class="px-3 py-1.5 rounded-md text-sm font-medium bg-indigo-600 text-white">{{ __('Summary') }}</span>
                    </div>
                    <form method="GET" action="{{ route('records.timesheets.summary') }}" class="flex flex-wrap items-center gap-2">
                        <select name="month" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-32">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                        <select name="year" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-28">
                            @for($y = date('Y'); $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <input type="hidden" name="category" value="{{ $category }}">
                        <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">{{ __('Filter') }}</button>
                    </form>
                </div>

                {{-- Category tabs --}}
                <div class="flex flex-wrap items-center gap-2 mb-6">
                    <a href="{{ route('records.timesheets.summary', ['month' => $month, 'year' => $year, 'category' => 'all']) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium {{ $category === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        {{ __('All Staff') }}
                    </a>
                    @foreach(\App\Models\User::CATEGORIES as $key => $label)
                        <a href="{{ route('records.timesheets.summary', ['month' => $month, 'year' => $year, 'category' => $key]) }}"
                           class="px-3 py-1.5 rounded-md text-sm font-medium {{ $category === $key ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div class="text-sm font-bold text-gray-800">{{ __('MONTH') }}: {{ DateTime::createFromFormat('!m', $month)->format('M') }}-{{ substr($year, -2) }}</div>
                    <div class="text-base font-bold text-gray-900 uppercase tracking-wide">
                        {{ __('Timesheet Summary') }} - {{ $category === 'all' ? __('All Staff') : strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('records.timesheets.summary.export-excel', request()->only('month', 'year', 'category')) }}"
                           class="px-3 py-1.5 rounded-md text-sm bg-green-600 text-white hover:bg-green-700">
                            {{ __('Excel') }}
                        </a>
                        <a href="{{ route('records.timesheets.summary.export-pdf', request()->only('month', 'year', 'category')) }}"
                           class="px-3 py-1.5 rounded-md text-sm bg-red-600 text-white hover:bg-red-700">
                            {{ __('PDF') }}
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="border-collapse text-xs table-fixed" style="width: {{ 460 + ($staffCount * 80) }}px;">
                        <colgroup>
                            <col style="width: 40px;">
                            <col style="width: 180px;">
                            <col style="width: 60px;">
                            <col style="width: 60px;">
                            @foreach($displayStaff as $user)
                                <col style="width: 80px;">
                            @endforeach
                            <col style="width: 60px;">
                            <col style="width: 60px;">
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-100">
                                <th rowspan="3" class="border border-gray-300 px-2 py-1 text-left min-w-[40px]">{{ __('NO') }}</th>
                                <th rowspan="3" colspan="3" class="border border-gray-300 px-2 py-1 text-left min-w-[180px]">{{ __('ADMIN JOB') }}</th>
                                <th colspan="{{ $staffCount }}" class="border border-gray-300 px-2 py-1 text-center border-r-2 border-r-black">{{ __('HOURS') }}</th>
                                <th rowspan="3" class="border border-gray-300 px-2 py-1 text-center min-w-[60px]">{{ __('TOTAL') }}</th>
                                <th rowspan="3" class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 text-center min-w-[60px] bg-white"></th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th colspan="{{ $staffCount }}" class="border border-gray-300 px-2 py-1 text-center min-w-[100px] border-r-2 border-r-black">{{ strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}</th>
                            </tr>
                            <tr class="bg-gray-100">
                                @foreach($displayStaff as $user)
                                    <th class="{{ $staffCellClass }}{{ $loop->last ? ' border-r-2 border-r-black' : '' }}">{{ $user['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adminTypes as $type => $label)
                                <tr>
                                    <td class="border border-gray-300 px-2 py-1 text-center">{{ $loop->iteration }}</td>
                                    <td colspan="3" class="border border-gray-300 px-2 py-1">{{ $label }}</td>
                                    @php $rowTotal = 0; @endphp
                                    @foreach($displayStaff as $user)
                                        @php $value = $user['id'] ? ($adminHours[$type][$user['id']] ?? 0) : 0; $rowTotal += $value; @endphp
                                        <td class="{{ $staffCellClass }}">{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                                    @endforeach
                                    <td class="border border-gray-300 px-2 py-1 text-center font-semibold">{{ $rowTotal > 0 ? number_format($rowTotal, 2) : '' }}</td>
                                    <td class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 text-center bg-white"></td>
                                </tr>
                            @endforeach

                            {{-- Total Admin Job row --}}
                            <tr class="bg-gray-200 font-bold">
                                <td class="border border-gray-300 px-2 py-1"></td>
                                <td colspan="3" class="border border-gray-300 px-2 py-1">{{ __('TOTAL ADMIN JOB') }}</td>
                                @php $grandAdminTotal = 0; @endphp
                                @foreach($displayStaff as $user)
                                    @php
                                        $userAdminTotal = 0;
                                        if ($user['id']) {
                                            foreach ($adminHours as $type => $hours) {
                                                $userAdminTotal += $hours[$user['id']] ?? 0;
                                            }
                                        }
                                        $grandAdminTotal += $userAdminTotal;
                                    @endphp
                                    <td class="{{ $staffCellClass }}">{{ $userAdminTotal > 0 ? number_format($userAdminTotal, 1) : '' }}</td>
                                @endforeach
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ $grandAdminTotal > 0 ? number_format($grandAdminTotal, 2) : '' }}</td>
                                <td class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 bg-white"></td>
                            </tr>

                            {{-- Project Code header --}}
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-2 py-1 text-left min-w-[40px]">{{ __('NO') }}</th>
                                <th class="border border-gray-300 px-2 py-1 text-left min-w-[180px]">{{ __('PROJECT CODE') }}</th>
                                <th colspan="2" class="border border-gray-300 px-2 py-1 text-center min-w-[120px]">{{ __('TIME / COST') }}</th>
                                @foreach($displayStaff as $user)
                                    <th class="{{ $staffCellClass }}{{ $loop->last ? ' border-r-2 border-r-black' : '' }}"></th>
                                @endforeach
                                <th class="border border-gray-300 px-2 py-1 text-center min-w-[60px]">{{ __('TOTAL') }}</th>
                                <th class="border border-gray-300 px-2 py-1 text-center min-w-[60px]"></th>
                            </tr>

                            {{-- Project rows --}}
                            @forelse($projects as $key => $project)
                                @php
                                    $projectTotal = 0;
                                    foreach ($displayStaff as $user) {
                                        if ($user['id']) {
                                            $projectTotal += ($project['hours'][$user['id']]['normal_nc'] ?? 0)
                                                + ($project['hours'][$user['id']]['normal_cobq'] ?? 0)
                                                + ($project['hours'][$user['id']]['ot_nc'] ?? 0)
                                                + ($project['hours'][$user['id']]['ot_cobq'] ?? 0);
                                        }
                                    }
                                @endphp
                                @foreach($rowHourTypes as $rIdx => $rowType)
                                    <tr>
                                        @if($rIdx === 0)
                                            <td rowspan="4" class="border border-gray-300 px-2 py-1 text-center align-top border-b-2 border-b-black">{{ $loop->parent->iteration }}</td>
                                            <td rowspan="4" class="border border-gray-300 px-2 py-1 align-top border-b-2 border-b-black">
                                                <div class="font-bold">{{ $project['code'] }}</div>
                                                <div class="text-[9px] text-gray-500">{{ $project['name'] }}</div>
                                            </td>
                                        @endif
                                        @if($rowType['group_span'] > 0)
                                            <td rowspan="{{ $rowType['group_span'] }}" class="border border-gray-300 px-2 py-1 text-center align-top{{ $rIdx === 2 ? ' border-b-2 border-b-black' : '' }}">{{ $rowType['group'] }}</td>
                                        @endif
                                        <td class="border border-gray-300 px-2 py-1 text-center{{ $rIdx === 3 ? ' border-b-2 border-b-black' : '' }}">{{ $rowType['cost'] }}</td>
                                        @php $rowTotal = 0; @endphp
                                        @foreach($displayStaff as $user)
                                            @php $value = $user['id'] ? ($project['hours'][$user['id']][$rowType['key']] ?? 0) : 0; $rowTotal += $value; @endphp
                                            <td class="{{ $staffCellClass }}{{ $rIdx === 3 ? ' border-b-2 border-b-black' : '' }}">{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                                        @endforeach
                                        <td class="border border-gray-300 px-2 py-1 text-center font-semibold{{ $rIdx === 3 ? ' border-b-2 border-b-black' : '' }}">{{ $rowTotal > 0 ? number_format($rowTotal, 2) : '' }}</td>
                                        @if($rIdx === 0)
                                            <td rowspan="4" class="border border-gray-300 px-2 py-1 text-center align-middle border-b-2 border-b-black">{{ $projectTotal > 0 ? number_format($projectTotal, 2) : '' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ $staffCount + 6 }}" class="border border-gray-300 px-4 py-3 text-center text-gray-500">{{ __('No project data found for this category and month.') }}</td>
                                </tr>
                            @endforelse

                            {{-- Summary rows --}}
                            <tr class="bg-sky-100 font-bold">
                                <td colspan="4" class="border border-gray-300 px-2 py-1 text-[11px]">{{ __('TOTAL EXTERNAL PROJECT') }}</td>
                                @php $grandExternal = 0; @endphp
                                @foreach($displayStaff as $user)
                                    @php $value = $user['id'] ? ($summary[$user['id']]['total_external_project'] ?? 0) : 0; $grandExternal += $value; @endphp
                                    <td class="{{ $staffCellClass }}">{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                                @endforeach
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ $grandExternal > 0 ? number_format($grandExternal, 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ $grandExternal > 0 ? number_format($grandExternal, 2) : '' }}</td>
                            </tr>
                            <tr class="bg-green-100 font-bold">
                                <td colspan="4" class="border border-gray-300 px-2 py-1 text-[11px]">{{ __('TOTAL WORKING HOURS') }}</td>
                                @php $grandWorking = 0; @endphp
                                @foreach($displayStaff as $user)
                                    @php $value = $user['id'] ? ($summary[$user['id']]['total_working_hours'] ?? 0) : 0; $grandWorking += $value; @endphp
                                    <td class="{{ $staffCellClass }}">{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                                @endforeach
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ $grandWorking > 0 ? number_format($grandWorking, 2) : '' }}</td>
                                <td class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 bg-white"></td>
                            </tr>
                            <tr class="bg-gray-100 font-semibold">
                                <td colspan="4" class="border border-gray-300 px-2 py-1 text-[11px]">{{ __('HOURS AVAILABLE') }}</td>
                                @php $grandAvailable = 0; @endphp
                                @foreach($displayStaff as $user)
                                    @php
                                        $value = $user['id'] ? ($summary[$user['id']]['hours_available'] ?? 0) : 0;
                                        $grandAvailable += $value;
                                    @endphp
                                    <td class="{{ $staffCellClass }}">{{ number_format($value, 1) }}</td>
                                @endforeach
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ number_format($grandAvailable, 2) }}</td>
                                <td class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 bg-white"></td>
                            </tr>
                            <tr class="bg-sky-100 font-bold">
                                <td colspan="4" class="border border-gray-300 px-2 py-1 text-[11px]">{{ __('OVERTIME') }}</td>
                                @php $grandOvertime = 0; @endphp
                                @foreach($displayStaff as $user)
                                    @php
                                        $userWorking = $user['id'] ? ($summary[$user['id']]['total_working_hours'] ?? 0) : 0;
                                        $userAvailable = $user['id'] ? ($summary[$user['id']]['hours_available'] ?? 0) : 0;
                                        $value = $userWorking - $userAvailable;
                                        $grandOvertime += $value;
                                    @endphp
                                    <td class="{{ $staffCellClass }} {{ $value < 0 ? 'text-red-600' : '' }}">{{ number_format($value, 1) }}</td>
                                @endforeach
                                <td class="border border-gray-300 px-2 py-1 text-center {{ $grandOvertime < 0 ? 'text-red-600' : '' }}">{{ number_format($grandOvertime, 2) }}</td>
                                <td class="border-t-0 border-r-0 border-b-0 border-l border-gray-300 px-2 py-1 bg-white"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
