<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if(isset($user) && $user->id !== auth()->id())
                {{ strtoupper($user->name) }} HISTORY
            @else
                {{ __('History') }}
            @endif
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto">

        {{-- Back button when viewing another user's history --}}
        @if(isset($user) && $user->id !== auth()->id())
            <div class="mb-4">
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to User Management
                </a>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <a href="{{ route('history.index', ['tab' => 'submissions']) }}"
                   class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-medium
                          {{ $tab === 'submissions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    My Submissions
                </a>
                @if($isApprover)
                    <a href="{{ route('history.index', ['tab' => 'approvals']) }}"
                       class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-medium
                              {{ $tab === 'approvals' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        My Approval Actions
                    </a>
                @endif
            </nav>
        </div>

        {{-- Tab 1: My Submissions --}}
        @if($tab === 'submissions')
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('history.index') }}" class="flex flex-wrap items-end gap-4 mb-6">
                        <input type="hidden" name="tab" value="submissions">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>All</option>
                                <option value="timesheet" {{ $typeFilter === 'timesheet' ? 'selected' : '' }}>Timesheet</option>
                                <option value="ot_form" {{ $typeFilter === 'ot_form' ? 'selected' : '' }}>OT Form</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
                                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                            Filter
                        </button>
                        @if($typeFilter !== 'all' || $statusFilter !== 'all')
                            <a href="{{ route('history.index', ['tab' => 'submissions']) }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                Clear filters
                            </a>
                        @endif
                    </form>

                    {{-- Submissions Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Updated</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($submissions as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item['type_badge'] }}">
                                                {{ $item['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            {{ $item['description'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item['status_badge'] }}">
                                                {{ $item['status_label'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ $item['submitted_at'] ? $item['submitted_at']->format('d M Y, h:i A') : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ $item['updated_at']->format('d M Y, h:i A') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ $item['view_url'] }}" class="text-indigo-600 hover:text-indigo-900 mr-2">View</a>
                                            @if($item['type'] === 'OT Form' && isset($item['ot_form_id']))
                                                <a href="{{ route('ot-forms.export-excel', $item['ot_form_id']) }}" class="text-green-600 hover:text-green-900 mr-2">Excel</a>
                                                <a href="{{ route('ot-forms.export-pdf', $item['ot_form_id']) }}" class="text-blue-600 hover:text-blue-900">PDF</a>
                                            @elseif($item['type'] === 'Timesheet' && isset($item['timesheet_id']))
                                                <a href="{{ route('timesheets.export-excel', $item['timesheet_id']) }}" class="text-green-600 hover:text-green-900 mr-2">Excel</a>
                                                <a href="{{ route('timesheets.export-pdf', $item['timesheet_id']) }}" class="text-blue-600 hover:text-blue-900">PDF</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                            No submissions found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tab 2: My Approval Actions --}}
        @if($tab === 'approvals' && $isApprover)
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('history.index') }}" class="flex flex-wrap items-end gap-4 mb-6">
                        <input type="hidden" name="tab" value="approvals">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="approval_type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="all" {{ $approvalTypeFilter === 'all' ? 'selected' : '' }}>All</option>
                                <option value="timesheet" {{ $approvalTypeFilter === 'timesheet' ? 'selected' : '' }}>Timesheet</option>
                                <option value="ot_form" {{ $approvalTypeFilter === 'ot_form' ? 'selected' : '' }}>OT Form</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                            <select name="action" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="all" {{ $actionFilter === 'all' ? 'selected' : '' }}>All</option>
                                <option value="approved" {{ $actionFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $actionFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                            Filter
                        </button>
                        @if($approvalTypeFilter !== 'all' || $actionFilter !== 'all')
                            <a href="{{ route('history.index', ['tab' => 'approvals']) }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                Clear filters
                            </a>
                        @endif
                    </form>

                    {{-- Approval Actions Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">View</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($approvalActions as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item['type_badge'] }}">
                                                {{ $item['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            {{ $item['staff_name'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ $item['description'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item['action_badge'] }}">
                                                {{ ucfirst($item['action']) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title="{{ $item['remarks'] }}">
                                            {{ $item['remarks'] ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ $item['acted_at'] ? $item['acted_at']->format('d M Y, h:i A') : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ $item['view_url'] }}" class="text-indigo-600 hover:text-indigo-900 mr-2">View</a>
                                            @if($item['type'] === 'OT Form' && isset($item['ot_form_id']))
                                                <a href="{{ route('ot-forms.export-excel', $item['ot_form_id']) }}" class="text-green-600 hover:text-green-900 mr-2">Excel</a>
                                                <a href="{{ route('ot-forms.export-pdf', $item['ot_form_id']) }}" class="text-blue-600 hover:text-blue-900">PDF</a>
                                            @elseif($item['type'] === 'Timesheet' && isset($item['timesheet_id']))
                                                <a href="{{ route('timesheets.export-excel', $item['timesheet_id']) }}" class="text-green-600 hover:text-green-900 mr-2">Excel</a>
                                                <a href="{{ route('timesheets.export-pdf', $item['timesheet_id']) }}" class="text-blue-600 hover:text-blue-900">PDF</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                            No approval actions found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
