<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Timesheet Approvals') }}</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto">
        @include('approvals.timesheets._nav')

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="overflow-x-auto">
                    @include('approvals.timesheets._table', ['mode' => 'approved'])
                </div>
                <div class="mt-4">{{ $timesheets->links() }}</div>
            </div>
        </div>
    </div>

    <x-help-button title="Timesheet Approval Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Timesheet Approval</h3>
            <p class="mb-3">Review and approve or reject timesheets submitted by your team members.</p>
            <h4 class="font-semibold text-gray-900 mb-1">How to use</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Review</strong> — Click <strong>"Review"</strong> button to view full timesheet details</li>
                <li><strong>Approve</strong> — Sign and approve timesheet</li>
                <li><strong>Reject</strong> — Reject with notes for staff to correct</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
