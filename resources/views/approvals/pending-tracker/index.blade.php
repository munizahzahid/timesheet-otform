<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pending Tracker') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                {{-- Tabs --}}
                <div class="mb-6 border-b border-gray-200">
                    <nav class="flex space-x-8" aria-label="Pending Tracker Tabs">
                        <a href="{{ route('approvals.pending-tracker.index', ['tab' => 'timesheets']) }}"
                           class="py-4 px-1 border-b-2 text-sm font-medium transition-colors {{ $tab === 'timesheets' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            {{ __('Timesheets') }}
                        </a>
                        <a href="{{ route('approvals.pending-tracker.index', ['tab' => 'ot-forms']) }}"
                           class="py-4 px-1 border-b-2 text-sm font-medium transition-colors {{ $tab === 'ot-forms' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            {{ __('OT Forms') }}
                        </a>
                    </nav>
                </div>

                {{-- Filters --}}
                <form method="GET" action="{{ route('approvals.pending-tracker.index') }}" class="mb-6 flex flex-wrap gap-4">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" id="status" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-56">
                            <option value="">{{ __('All Pending Statuses') }}</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $statusFilter == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="staff" class="block text-xs font-medium text-gray-700 mb-1">{{ __('Staff') }}</label>
                        <input type="text" name="staff" id="staff" value="{{ $staffFilter }}"
                               placeholder="Name or staff no"
                               class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-56">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('approvals.pending-tracker.index', ['tab' => $tab]) }}" class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Month / Year') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Pending With') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Submitted At') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($items as $item)
                                @php
                                    $isOt = $tab === 'ot-forms';
                                    $user = $item->user;

                                    if ($isOt) {
                                        $pendingWith = match($item->status) {
                                            'pending_manager' => $user->otApprover?->name ?? $user->otExecApprover?->name ?? $user->otNonExecApprover?->name ?? '-',
                                            'pending_hr' => 'HR',
                                            'pending_gm' => $user->otFinalApprover?->name ?? $user->otExecFinalApprover?->name ?? $user->otNonExecFinalApprover?->name ?? '-',
                                            default => '-',
                                        };
                                        $submittedAt = $item->plan_submitted_at;
                                        $route = route('approvals.ot-forms.show', $item);
                                    } else {
                                        $pendingWith = match($item->status) {
                                            'pending_hod' => $user->timesheetHodApprover?->name ?? '-',
                                            'pending_l1' => $user->timesheetApprover?->name ?? '-',
                                            default => '-',
                                        };
                                        $submittedAt = $item->submitted_at;
                                        $route = route('approvals.timesheets.show', $item);
                                    }

                                    $badgeClass = match($item->status) {
                                        'pending_hod', 'pending_manager' => 'bg-yellow-100 text-yellow-800',
                                        'pending_hr' => 'bg-cyan-100 text-cyan-800',
                                        'pending_l1', 'pending_gm' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $user?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ \DateTime::createFromFormat('!m', $item->month)->format('F') }} {{ $item->year }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                            {{ $item->status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $pendingWith }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $submittedAt?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ $route }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Review') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('No pending items found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $items->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
