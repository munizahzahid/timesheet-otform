<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/jpeg" href="{{ asset('images/TS.png') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            [x-cloak] { display: none !important; }
            #main-content { transition: margin-left 0.3s ease-in-out; }
            @media (min-width: 1024px) {
                body > #main-content { margin-left: 16rem !important; }
                body > #main-content.collapsed-main { margin-left: 5rem !important; }
            }
            .sidebar-collapsed .nav-label,
            .sidebar-collapsed .section-label,
            .sidebar-collapsed .profile-detail { display: none !important; }
            .sidebar-collapsed .badge-count { display: none !important; }
            .sidebar-collapsed .badge-dot { display: inline-flex !important; }
            .badge-dot { display: none; }
            .sidebar-collapsed nav a,
            .sidebar-collapsed nav button { justify-content: center !important; padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            .sidebar-collapsed .brand-block { display: none !important; }
            .sidebar-collapsed .profile-card > div { justify-content: center !important; }
            #main-content main .max-w-7xl,
            #main-content main .max-w-6xl,
            #main-content main .max-w-5xl,
            #main-content main .max-w-4xl { max-width: none !important; width: 100% !important; }
        </style>
        @stack('styles')
    </head>
    <body class="font-sans antialiased bg-gray-50">
        @php
            $authUser = Auth::user();
            $isOtApprover = \App\Models\User::where('ot_approver_id', $authUser->id)->orWhere('ot_final_approver_id', $authUser->id)->exists();
            $isTimesheetApprover = \App\Models\User::where('timesheet_hod_approver_id', $authUser->id)->orWhere('timesheet_approver_id', $authUser->id)->exists();
            $viewingOtherUser = request()->has('user_id');

            $sidebarProps = [
                'logoUrl' => asset('images/Logo TSSB.jpeg'),
                'user' => [
                    'name' => $authUser->name,
                    'initial' => strtoupper(substr($authUser->name, 0, 1)),
                    'designation' => $authUser->designation ?? $authUser->email,
                    'role' => $authUser->role,
                    'isOtApprover' => $isOtApprover,
                    'isTimesheetApprover' => $isTimesheetApprover,
                    'profileUrl' => route('profile.edit'),
                    'logoutUrl' => route('logout'),
                ],
                'csrfToken' => csrf_token(),
                'isAdmin' => $authUser->isAdmin(),
                'showApprovals' => $authUser->role === 'admin' || $authUser->role === 'hr' || $isOtApprover || $isTimesheetApprover,
                'counts' => [
                    'hrNewStatus' => $hrNewStatusCount ?? 0,
                    'pendingOtApproval' => $pendingOtApprovalCount ?? 0,
                    'pendingTimesheetApproval' => $pendingTimesheetApprovalCount ?? 0,
                ],
                'routes' => [
                    'dashboard' => route('dashboard'),
                    'timesheets' => route('timesheets.index'),
                    'project' => Route::has('project.dashboard') ? route('project.dashboard') : null,
                    'history' => request()->has('user_id') ? route('history.index', ['user_id' => request('user_id')]) : route('history.index'),
                    'pendingTracker' => route('approvals.pending-tracker.index'),
                    'otApprovals' => route('approvals.ot-forms.index'),
                    'timesheetApprovals' => route('approvals.timesheets.index'),
                    'adminUsers' => route('admin.users.index'),
                    'adminProjectCodes' => route('admin.project-codes.index'),
                    'adminHolidays' => route('admin.holidays.index'),
                    'adminDesknetSync' => route('admin.desknet-sync.index'),
                    'adminAudit' => route('admin.audit.index'),
                    'adminSettings' => route('admin.settings.index'),
                ],
                'active' => [
                    'dashboard' => !$viewingOtherUser && request()->routeIs('dashboard'),
                    'timesheets' => !$viewingOtherUser && (request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*') || request()->routeIs('training-attendance.*')),
                    'project' => !$viewingOtherUser && request()->routeIs('project.*'),
                    'history' => !$viewingOtherUser && request()->routeIs('history.*'),
                    'pendingTracker' => !$viewingOtherUser && request()->routeIs('approvals.pending-tracker.*'),
                    'otApprovals' => !$viewingOtherUser && request()->routeIs('approvals.ot-forms.*'),
                    'timesheetApprovals' => !$viewingOtherUser && request()->routeIs('approvals.timesheets.*'),
                    'admin' => !$viewingOtherUser && request()->routeIs('admin.*'),
                    'adminUsers' => !$viewingOtherUser && request()->routeIs('admin.users.*'),
                    'adminProjectCodes' => !$viewingOtherUser && request()->routeIs('admin.project-codes.*'),
                    'adminHolidays' => !$viewingOtherUser && request()->routeIs('admin.holidays.*'),
                    'adminDesknetSync' => !$viewingOtherUser && request()->routeIs('admin.desknet-sync.*'),
                    'adminAudit' => !$viewingOtherUser && request()->routeIs('admin.audit.*'),
                    'adminSettings' => !$viewingOtherUser && request()->routeIs('admin.settings.*'),
                ],
                'adminOpen' => request()->routeIs('admin.*'),
                'settingsOpen' => request()->routeIs('profile.*') || request()->routeIs('admin.settings.*'),
            ];
        @endphp

        {{-- Sidebar mount --}}
        <script type="application/json" id="sidebar-props">@json($sidebarProps)</script>
        <div id="sidebar-root"></div>

        {{-- Main Content Area (pushed right on desktop) --}}
        <div id="main-content" class="min-h-screen flex flex-col">
                {{-- Top Navbar --}}
                <header class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
                    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-3">
                        <div class="flex items-center gap-3">
                            {{-- Mobile menu button --}}
                            <button onclick="window.dispatchEvent(new CustomEvent('toggle-sidebar'))"
                                    class="lg:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>

                            {{-- Page-level top-left actions (e.g. Back to List) --}}
                            @stack('top-left-actions')

                            {{-- Home button --}}
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                Home
                            </a>
                        </div>

                        {{-- Optional Action Buttons --}}
                        @isset($actionButtons)
                            <div class="flex items-center gap-2">
                                {{ $actionButtons }}
                            </div>
                        @endisset

                        {{-- User info --}}
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ Auth::user()->designation ?? Auth::user()->email }}</p>
                            </div>
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-700 to-blue-900 rounded-full flex items-center justify-center text-white font-bold shadow-lg text-sm">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        </div>
                    </div>
                </header>

                @stack('sub-navbar')

            {{-- Page Content --}}
            <main class="flex-1 w-full p-4 sm:p-6 lg:p-8">
                {{-- Page Title Section --}}
                @isset($pageTitle)
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
                    </div>
                @elseif(isset($header))
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $header }}</h1>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
