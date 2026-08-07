<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    @php
        $lastSync = \App\Models\DesknetSyncLog::where('status', 'success')->orderByDesc('completed_at')->first();

        $dashboardProps = [
            'user' => [
                'name' => $user->name,
                'role' => str_replace('_', ' ', ucfirst($user->role)),
                'department' => $user->department?->name,
            ],
            'isAdmin' => $user->isAdmin(),
            'canApproveTimesheets' => $canApproveTimesheets,
            'canApproveOtForms' => $canApproveOtForms,
            'activeUsersCount' => \App\Models\User::where('is_active', true)->count(),
            'activeProjectsCount' => \App\Models\Project::where('is_active', true)->count(),
            'lastSync' => $lastSync ? $lastSync->completed_at->diffForHumans() : 'Never',
            'pendingTimesheetApprovalCount' => $pendingTimesheetApprovalCount,
            'pendingOtApprovalCount' => $pendingOtApprovalCount,
            'availableMonths' => $availableMonths->toArray(),
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'selectedMonthNumber' => $selectedMonthNumber,
            'activeTrainingSessions' => $activeTrainingSessions->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => $s->is_active,
                'attended' => $s->attended,
                'training_date' => $s->training_date->format('d M Y'),
                'time_in' => $s->time_in?->format('H:i'),
                'time_out' => $s->time_out?->format('H:i'),
                'venue' => $s->venue,
            ])->values()->toArray(),
            'recentActions' => $recentActions->map(fn ($a) => [
                'type' => class_basename($a->model_type) === 'Timesheet' ? 'Timesheet' : 'OT Form',
                'action' => $a->action,
                'description' => $a->description,
                'time' => $a->created_at->diffForHumans(),
            ])->values()->toArray(),
            'recentUpdates' => $recentUpdates->map(fn ($u) => [
                'type' => $u['type'] === 'timesheet' ? 'Timesheet' : 'OT Form',
                'action' => ucfirst($u['action']),
                'status_label' => $u['model']?->status_label,
                'actor_name' => $u['actor']?->name,
                'time' => $u['time']?->diffForHumans(),
            ])->values()->toArray(),
            'otMonthlyData' => $otMonthlyData->map(fn ($r) => ['label' => $r->label, 'hours' => (float) $r->hours])->toArray(),
            'otProjectData' => $otProjectData->map(fn ($r) => ['label' => $r->label, 'hours' => (float) $r->hours])->toArray(),
            'otStaffData' => $otStaffData->map(fn ($r) => ['label' => $r->label, 'hours' => (float) $r->hours])->toArray(),
            'dashboardUrl' => route('dashboard'),
            'routes' => [
                'adminUsers' => route('admin.users.index'),
                'adminProjectCodes' => route('admin.project-codes.index'),
                'adminDesknetSync' => route('admin.desknet-sync.index'),
                'timesheetApprovals' => route('approvals.timesheets.index'),
                'otApprovals' => route('approvals.ot-forms.index'),
                'trainingAttendance' => route('training-attendance.index'),
            ],
        ];
    @endphp

    <div class="max-w-7xl mx-auto">
        <script type="application/json" id="main-dashboard-props">@json($dashboardProps)</script>
        <div id="main-dashboard"></div>
    </div>

    <x-help-button title="Dashboard Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Dashboard Overview</h3>
            <p class="mb-3">This is your main page showing a quick summary of your account.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Quick Links</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Timesheet</strong> — View and manage your monthly timesheets</li>
                <li><strong>OT Forms</strong> — Submit and track overtime requests</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">HR Activity</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Recent Actions</strong> — Your own create/edit/delete activity on Timesheets and OT Forms</li>
                <li><strong>Recent Updates</strong> — Status changes on your Timesheets and OT Forms</li>
            </ul>
            @if($canApproveTimesheets || $canApproveOtForms)
                <h4 class="font-semibold text-gray-900 mb-1">Pending Approvals</h4>
                <p class="mb-3">As an approver, you’ll see counts of Timesheets and OT Forms awaiting your approval. Click a card to review them.</p>
            @endif
            @if($user->isAdmin())
                <h4 class="font-semibold text-gray-900 mb-1">Admin Cards</h4>
                <p>Cards below show active users, project codes, and last Desknet sync status. Click any card to manage that section.</p>
            @endif
            <h4 class="font-semibold text-gray-900 mb-1">OT Analytics</h4>
            <p>Charts above show approved OT hours by project, staff, and month. Use the month filter to change the project and staff charts.</p>
        </x-slot>
    </x-help-button>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    @endpush
</x-app-layout>
