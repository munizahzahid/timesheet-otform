<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('OT Summary') }}</h2>
    </x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    @php
        $staffIds = $staff->pluck('id')->toArray();

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

        $grandTotal = 0;
        foreach ($totals as $total) {
            $grandTotal += $total;
        }
    @endphp

    <div class="py-6">
        <div class="max-w-[95%] mx-auto sm:px-4 lg:px-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 bg-white border-b border-gray-200">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('records.ot-forms.summary') }}" class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label for="month" class="block text-xs font-medium text-gray-700">Month</label>
                            <select name="month" id="month" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label for="year" class="block text-xs font-medium text-gray-700">Year</label>
                            <select name="year" id="year" class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach (range(date('Y') - 2, date('Y') + 1) as $y)
                                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">Filter</button>
                        </div>
                    </form>

                    {{-- Category tabs --}}
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach (['all' => 'All'] + \App\Models\User::CATEGORIES as $key => $label)
                            <a href="{{ route('records.ot-forms.summary', ['month' => $month, 'year' => $year, 'category' => $key]) }}"
                               class="px-3 py-1.5 rounded-md text-sm font-medium {{ $category === $key ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div class="text-sm font-bold text-gray-800">{{ __('MONTH') }}: {{ DateTime::createFromFormat('!m', $month)->format('M') }}-{{ substr($year, -2) }}</div>
                        <div class="text-base font-bold text-gray-900 uppercase tracking-wide">
                            {{ __('OT Summary') }} - {{ $category === 'all' ? __('All Staff') : strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('records.ot-forms.summary.export-excel', request()->only('month', 'year', 'category')) }}"
                               class="px-3 py-1.5 rounded-md text-sm bg-green-600 text-white hover:bg-green-700">
                                {{ __('Excel') }}
                            </a>
                            <a href="{{ route('records.ot-forms.summary.export-pdf', request()->only('month', 'year', 'category')) }}"
                               class="px-3 py-1.5 rounded-md text-sm bg-red-600 text-white hover:bg-red-700">
                                {{ __('PDF') }}
                            </a>
                        </div>
                    </div>

                    {{-- OT Summary Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-400 text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-400 px-2 py-1 text-center" style="min-width: 50px;">NO</th>
                                    <th class="border border-gray-400 px-2 py-1 text-center" style="min-width: 220px;">PROJECT</th>
                                    @foreach ($displayStaff as $user)
                                        <th class="border border-gray-400 px-2 py-1 text-center" style="min-width: 70px;">{{ $user['name'] }}</th>
                                    @endforeach
                                    <th class="border border-gray-400 px-2 py-1 text-center" style="min-width: 90px;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $idx = 1; @endphp
                                @forelse ($projects as $project)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-400 px-2 py-1 text-center">{{ $idx++ }}</td>
                                        <td class="border border-gray-400 px-2 py-1">
                                            <div class="font-semibold">{{ $project['code'] }}</div>
                                            <div class="text-xs text-gray-600">{{ $project['name'] }}</div>
                                        </td>
                                        @php $rowTotal = 0; @endphp
                                        @foreach ($displayStaff as $user)
                                            @php
                                                $value = $user['id'] ? ($project['hours'][$user['id']] ?? 0) : 0;
                                                $rowTotal += $value;
                                            @endphp
                                            <td class="border border-gray-400 px-2 py-1 text-center">
                                                {{ $value != 0 ? number_format($value, 2) : '' }}
                                            </td>
                                        @endforeach
                                        <td class="border border-gray-400 px-2 py-1 text-center font-semibold">{{ number_format($rowTotal, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($displayStaff) + 3 }}" class="border border-gray-400 px-4 py-4 text-center text-gray-500">
                                            No OT data found for this period.
                                        </td>
                                    </tr>
                                @endforelse

                                <tr class="bg-blue-100 font-bold">
                                    <td class="border border-gray-400 px-2 py-1"></td>
                                    <td class="border border-gray-400 px-2 py-1">TOTAL OT HOURS</td>
                                    @foreach ($displayStaff as $user)
                                        @php $userTotal = $user['id'] ? ($totals[$user['id']] ?? 0) : 0; @endphp
                                        <td class="border border-gray-400 px-2 py-1 text-center">{{ $userTotal != 0 ? number_format($userTotal, 2) : '' }}</td>
                                    @endforeach
                                    <td class="border border-gray-400 px-2 py-1 text-center">{{ number_format($grandTotal, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
