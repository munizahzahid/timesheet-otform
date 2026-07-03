<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('All Approved Timesheets') }}</h2>
    </x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    <div class="max-w-6xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('records.timesheets') }}" class="px-3 py-1.5 rounded-md text-sm font-medium bg-indigo-600 text-white">{{ __('Timesheet') }}</a>
                        <a href="{{ route('records.ot-forms') }}" class="px-3 py-1.5 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">{{ __('OT Form') }}</a>
                    </div>
                    <form method="GET" action="{{ route('records.timesheets') }}" class="flex flex-wrap items-center gap-2">
                        <select name="month" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-32">
                            <option value="">{{ __('All Months') }}</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                        <select name="year" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-28">
                            <option value="">{{ __('All Years') }}</option>
                            @for($y = date('Y'); $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">{{ __('Filter') }}</button>
                        @if($month || $year)
                            <a href="{{ route('records.timesheets') }}" class="px-4 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 text-center">{{ __('Clear') }}</a>
                        @endif
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Month / Year') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Department') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Approved At') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($timesheets as $timesheet)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $timesheet->user->name }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $timesheet->user->department?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $approvedAt[$timesheet->id] ?? null ? $approvedAt[$timesheet->id]->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $timesheet->status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('records.timesheets.show', $timesheet) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                                            <a href="{{ route('timesheets.export-excel', $timesheet) }}" class="text-green-600 hover:text-green-800">{{ __('Excel') }}</a>
                                            <a href="{{ route('timesheets.export-pdf', $timesheet) }}" class="text-red-600 hover:text-red-800">{{ __('PDF') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('No approved timesheets found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $timesheets->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
