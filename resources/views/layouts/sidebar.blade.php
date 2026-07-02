{{-- Sidebar Navigation --}}
@php $viewingOtherUser = request()->has('user_id'); @endphp
<aside x-data="{ settingsOpen: {{ request()->routeIs('profile.*') ? 'true' : 'false' }}, adminOpen: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }"
       style="position:fixed;top:0;left:0;width:16rem;height:100vh;height:100dvh;background-color:#1e3a8a;color:#d1d5db;"
       class="z-30 text-gray-300 flex flex-col transform transition-transform duration-200 ease-in-out lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Logo / Brand --}}
    <div class="flex items-center px-6 py-5 border-b border-blue-800 bg-blue-900/50 backdrop-blur">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/Logo TSSB.jpeg') }}" alt="Talent Synergy Sdn Bhd" class="h-10 w-auto">
        </a>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                  {{ !$viewingOtherUser && request()->routeIs('dashboard') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('dashboard') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        {{-- HR --}}
        <a href="{{ route('timesheets.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                  {{ !$viewingOtherUser && (request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*')) ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && (request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*')) ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span class="flex-1">HR</span>
            @if($hrNewStatusCount > 0)
                <span class="inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white" style="width: 20px; height: 20px;">
                    {{ $hrNewStatusCount }}
                </span>
            @endif
        </a>

        {{-- Project (Admin Only) --}}
        @if(Auth::user()->isAdmin() && Route::has('admin.project.dashboard'))
            <a href="{{ route('admin.project.dashboard') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                      {{ !$viewingOtherUser && request()->routeIs('admin.project.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('admin.project.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                Project
            </a>
        @endif

        {{-- Finance (Placeholder) --}}
        <a href="#"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group text-gray-300 hover:bg-blue-800 hover:text-white">
            <svg class="w-5 h-5 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Finance
        </a>

        {{-- Design (Placeholder) --}}
        <a href="#"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group text-gray-300 hover:bg-blue-800 hover:text-white">
            <svg class="w-5 h-5 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
            </svg>
            Design
        </a>

        {{-- History --}}
        <a href="{{ request()->has('user_id') ? route('history.index', ['user_id' => request('user_id')]) : route('history.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                  {{ !$viewingOtherUser && request()->routeIs('history.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('history.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            History
        </a>

        {{-- Approvals (for designated approvers, admin, and hr) --}}
        @php
            $user = Auth::user();
            $isOtApprover = \App\Models\User::where('ot_approver_id', $user->id)->orWhere('ot_final_approver_id', $user->id)->exists();
            $isTimesheetApprover = \App\Models\User::where('timesheet_hod_approver_id', $user->id)->orWhere('timesheet_approver_id', $user->id)->exists();
            $showApprovals = $user->role === 'admin' || $user->role === 'hr' || $isOtApprover || $isTimesheetApprover;
        @endphp
        @if($showApprovals)
            <div class="pt-4 pb-2">
                <p class="px-4 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Approvals</p>
            </div>

            @if($user->role === 'admin' || $user->role === 'hr' || $isOtApprover)
                <a href="{{ route('approvals.ot-forms.index') }}"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                          {{ !$viewingOtherUser && request()->routeIs('approvals.ot-forms.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('approvals.ot-forms.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1">OT Approvals</span>
                    @if($pendingOtApprovalCount > 0)
                        <span class="inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white" style="width: 20px; height: 20px;">
                            {{ $pendingOtApprovalCount }}
                        </span>
                    @endif
                </a>
            @endif

            @if($user->role === 'admin' || $isTimesheetApprover)
                <a href="{{ route('approvals.timesheets.index') }}"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                          {{ !$viewingOtherUser && request()->routeIs('approvals.timesheets.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('approvals.timesheets.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="flex-1">Timesheet Approvals</span>
                    @if($pendingTimesheetApprovalCount > 0)
                        <span class="inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white" style="width: 20px; height: 20px;">
                            {{ $pendingTimesheetApprovalCount }}
                        </span>
                    @endif
                </a>
            @endif
        @endif

        {{-- Admin Dropdown --}}
        @if(Auth::user()->isAdmin())
            <div>
                <button @click="adminOpen = !adminOpen"
                        class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                               {{ !$viewingOtherUser && request()->routeIs('admin.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('admin.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Admin
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="adminOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div x-show="adminOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4">
                    <a href="{{ route('admin.users.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.users.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        Users
                    </a>
                    <a href="{{ route('admin.project-codes.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.project-codes.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Project Codes
                    </a>
                    <a href="{{ route('admin.holidays.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.holidays.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Holidays
                    </a>
                    <a href="{{ route('admin.desknet-sync.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.desknet-sync.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Desknet Sync
                    </a>
                    <a href="{{ route('admin.audit.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.audit.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Audit Logs
                    </a>
                </div>
            </div>
        @endif

        {{-- Settings / Profile Dropdown --}}
        <div class="pt-4 pb-2">
            <p class="px-4 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Account</p>
        </div>

        <div>
            <button @click="settingsOpen = !settingsOpen"
                    class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                           {{ !$viewingOtherUser && request()->routeIs('profile.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ !$viewingOtherUser && request()->routeIs('profile.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Profile
                </span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="settingsOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="settingsOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4">
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                          {{ !$viewingOtherUser && request()->routeIs('profile.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.settings.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ !$viewingOtherUser && request()->routeIs('admin.settings.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        System Settings
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full text-left px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-blue-800 hover:text-white transition-all duration-200">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>
</aside>
