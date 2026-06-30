@php
    $active = request()->routeIs('approvals.timesheets.index') ? 'pending' : 'approved';
@endphp

<div class="mb-6 border-b border-gray-200">
    <nav class="flex space-x-8" aria-label="Timesheet Approval Tabs">
        <a href="{{ route('approvals.timesheets.index') }}"
           class="py-4 px-1 border-b-2 text-sm font-medium transition-colors {{ $active === 'pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Pending Approval
        </a>
        <a href="{{ route('approvals.timesheets.approved') }}"
           class="py-4 px-1 border-b-2 text-sm font-medium transition-colors {{ $active === 'approved' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Approved Form
        </a>
    </nav>
</div>
