<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Welcome, {{ Auth::user()->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">
                    Role: <span class="font-medium">{{ str_replace('_', ' ', ucfirst(Auth::user()->role)) }}</span>
                    @if(Auth::user()->department)
                        | Department: <span class="font-medium">{{ Auth::user()->department->name }}</span>
                    @endif
                </p>
            </div>
        </div>

        @if(Auth::user()->isAdmin())
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <a href="{{ route('admin.users.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Users</h4>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Models\User::where('is_active', true)->count() }}</p>
                    <p class="text-xs text-gray-400 mt-1">Active users</p>
                </a>
                <a href="{{ route('admin.project-codes.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Project Codes</h4>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Models\ProjectCode::where('is_active', true)->count() }}</p>
                    <p class="text-xs text-gray-400 mt-1">Active projects</p>
                </a>
                <a href="{{ route('admin.desknet-sync.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition">
                    <h4 class="text-sm font-medium text-gray-500 uppercase">Last Sync</h4>
                    @php $lastSync = \App\Models\DesknetSyncLog::where('status','success')->orderByDesc('completed_at')->first(); @endphp
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $lastSync ? $lastSync->completed_at->diffForHumans() : 'Never' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Desknet sync status</p>
                </a>
            </div>
        @endif

        @if($canApproveTimesheets || $canApproveOtForms)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                @if($canApproveTimesheets)
                    <a href="{{ route('approvals.timesheets.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition">
                        <h4 class="text-sm font-medium text-gray-500 uppercase">Pending Timesheet Approvals</h4>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $pendingTimesheetApprovalCount }}</p>
                        <p class="text-xs text-gray-400 mt-1">Awaiting your approval</p>
                    </a>
                @endif
                @if($canApproveOtForms)
                    <a href="{{ route('approvals.ot-forms.index') }}" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition">
                        <h4 class="text-sm font-medium text-gray-500 uppercase">Pending OT Approvals</h4>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $pendingOtApprovalCount }}</p>
                        <p class="text-xs text-gray-400 mt-1">Awaiting your approval</p>
                    </a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Recent Actions --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recent Actions</h4>
                    <p class="text-xs text-gray-400 mt-1">Your recent Timesheet / OT Form activity</p>
                </div>
                <div class="p-6">
                    @if($recentActions->isEmpty())
                        <p class="text-sm text-gray-500">No recent actions.</p>
                    @else
                        <ul class="space-y-4">
                            @foreach($recentActions as $action)
                                <li class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            @if($action->model_type === \App\Models\Timesheet::class)
                                                Timesheet
                                            @else
                                                OT Form
                                            @endif
                                            <span class="text-gray-500">{{ $action->action }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $action->description }} &middot; {{ $action->created_at->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Recent Updates --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recent Updates</h4>
                    <p class="text-xs text-gray-400 mt-1">Status changes on your Timesheets / OT Forms</p>
                </div>
                <div class="p-6">
                    @if($recentUpdates->isEmpty())
                        <p class="text-sm text-gray-500">No recent updates.</p>
                    @else
                        <ul class="space-y-4">
                            @foreach($recentUpdates as $update)
                                <li class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            @if($update['type'] === 'timesheet')
                                                Timesheet
                                            @else
                                                OT Form
                                            @endif
                                            <span class="text-gray-500">{{ ucfirst($update['action']) }}</span>
                                        </p>
                                        @if($update['model'])
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                {{ $update['model']->status_label }}
                                                @if($update['actor'])
                                                    by {{ $update['actor']->name }}
                                                @endif
                                                &middot; {{ $update['time']->diffForHumans() }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
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
            @if(Auth::user()->isAdmin())
                <h4 class="font-semibold text-gray-900 mb-1">Admin Cards</h4>
                <p>Cards below show active users, project codes, and last Desknet sync status. Click any card to manage that section.</p>
            @endif
        </x-slot>
    </x-help-button>
</x-app-layout>
