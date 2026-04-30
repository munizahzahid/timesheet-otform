{{-- Sidebar Navigation --}}
<aside x-data="{ hrOpen: {{ request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*') ? 'true' : 'false' }}, financeOpen: false, designOpen: false, settingsOpen: {{ request()->routeIs('profile.*') ? 'true' : 'false' }} }"
       style="position:fixed;top:0;left:0;width:16rem;height:100vh;height:100dvh;background-color:#1e3a8a;color:#d1d5db;"
       class="z-30 text-gray-300 flex flex-col transform transition-transform duration-200 ease-in-out lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Logo / Brand --}}
    <div class="flex items-center gap-3 px-6 py-5 border-b border-blue-800 bg-blue-900/50 backdrop-blur">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/TALENT SYNERGY SDN BHD.png') }}" alt="Talent Synergy Sdn Bhd" class="h-8 w-auto rounded-full">
            <span class="text-white font-bold text-xl tracking-tight">TSSB Portal</span>
        </a>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                  {{ request()->routeIs('dashboard') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('dashboard') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        {{-- HR Dropdown --}}
        <div>
            <button @click="hrOpen = !hrOpen"
                    class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                           {{ request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('timesheets.*') || request()->routeIs('ot-forms.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    HR
                </span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="hrOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="hrOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4">
                <a href="{{ route('timesheets.index') }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                          {{ request()->routeIs('timesheets.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    Timesheet
                </a>
                <a href="{{ route('ot-forms.index') }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                          {{ request()->routeIs('ot-forms.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    OT Form
                </a>
            </div>
        </div>

        {{-- Finance Dropdown (Placeholder) --}}
        <div>
            <button @click="financeOpen = !financeOpen"
                    class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                           text-gray-300 hover:bg-blue-800 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Finance
                </span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="financeOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="financeOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4">
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-blue-800 hover:text-white transition-all duration-200">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports (Coming Soon)
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-blue-800 hover:text-white transition-all duration-200">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Budget (Coming Soon)
                </a>
            </div>
        </div>

        {{-- Design Dropdown (Placeholder) --}}
        <div>
            <button @click="designOpen = !designOpen"
                    class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                           text-gray-300 hover:bg-blue-800 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                    </svg>
                    Design
                </span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="designOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="designOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4">
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-blue-800 hover:text-white transition-all duration-200">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Assets (Coming Soon)
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-blue-800 hover:text-white transition-all duration-200">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                    </svg>
                    Templates (Coming Soon)
                </a>
            </div>
        </div>

        {{-- History --}}
        <a href="{{ route('history.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                  {{ request()->routeIs('history.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('history.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            History
        </a>

        {{-- Approvals (for managers/admin) --}}
        @if(Auth::user()->role === 'admin' || str_contains(strtolower(Auth::user()->designation ?? ''), 'manager') || str_contains(strtolower(Auth::user()->designation ?? ''), 'gm') || str_contains(strtolower(Auth::user()->designation ?? ''), 'ceo') || Auth::user()->canApproveOTFormLevel1() || Auth::user()->canApproveTimesheetHOD() || Auth::user()->canApproveTimesheetL1())
            <div class="pt-4 pb-2">
                <p class="px-4 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Approvals</p>
            </div>

            @if(Auth::user()->role === 'admin' || str_contains(strtolower(Auth::user()->designation ?? ''), 'manager') || str_contains(strtolower(Auth::user()->designation ?? ''), 'gm') || str_contains(strtolower(Auth::user()->designation ?? ''), 'ceo') || Auth::user()->canApproveOTFormLevel1())
                <a href="{{ route('approvals.ot-forms.index') }}"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                          {{ request()->routeIs('approvals.ot-forms.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('approvals.ot-forms.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    OT Approvals
                </a>
            @endif

            @if(Auth::user()->canApproveTimesheetHOD() || Auth::user()->canApproveTimesheetL1() || Auth::user()->role === 'ceo' || Auth::user()->role === 'admin')
                <a href="{{ route('approvals.timesheets.index') }}"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                          {{ request()->routeIs('approvals.timesheets.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('approvals.timesheets.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    Timesheet Approvals
                </a>
            @endif
        @endif

        {{-- Settings / Profile Dropdown --}}
        <div class="pt-4 pb-2">
            <p class="px-4 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Account</p>
        </div>

        <div>
            <button @click="settingsOpen = !settingsOpen"
                    class="flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group
                           {{ request()->routeIs('profile.*') ? 'bg-blue-700 text-white shadow-lg shadow-blue-700/40 border-l-4 border-blue-600' : 'text-gray-300 hover:bg-blue-800 hover:text-white' }}">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 transition-colors {{ request()->routeIs('profile.*') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                          {{ request()->routeIs('profile.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.settings.index') }}"
                       class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                              {{ request()->routeIs('admin.settings.*') ? 'bg-blue-700 text-white font-medium shadow-md' : 'text-gray-400 hover:bg-blue-800 hover:text-white' }}">
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

    {{-- User Profile Footer --}}
    <div class="border-t border-blue-800 px-4 py-4 bg-blue-900/50 backdrop-blur">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-700 to-blue-900 rounded-full flex items-center justify-center text-sm font-bold text-white shadow-lg">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->designation ?? Auth::user()->email }}</p>
            </div>
        </div>
    </div>
</aside>
