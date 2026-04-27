{{-- Sidebar Navigation --}}
<aside x-data="{ adminOpen: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }"
       class="fixed inset-y-0 left-0 z-30 w-60 bg-gray-900 text-gray-300 flex flex-col transform transition-transform duration-200 ease-in-out lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Logo / Brand --}}
    <div class="flex items-center gap-3 px-5 py-5 border-b border-gray-800">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <span class="text-white font-semibold text-lg tracking-tight">Timesheet</span>
        </a>
    </div>

    {{-- Search --}}
    <div class="px-4 py-3">
        <div class="relative">
            <svg class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" placeholder="Search..."
                   class="w-full bg-gray-800 border-0 rounded-lg text-sm text-gray-300 placeholder-gray-500 pl-10 pr-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
        </div>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
        {{-- Main Section --}}
        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Main</p>

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        {{-- Timesheets --}}
        <a href="{{ route('timesheets.index') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  {{ request()->routeIs('timesheets.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            Timesheets
        </a>

        {{-- OT Forms --}}
        <a href="{{ route('ot-forms.index') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  {{ request()->routeIs('ot-forms.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            OT Forms
        </a>

        {{-- Approvals (for managers/admin) --}}
        @if(Auth::user()->role === 'admin' || str_contains(strtolower(Auth::user()->designation ?? ''), 'manager') || str_contains(strtolower(Auth::user()->designation ?? ''), 'gm') || str_contains(strtolower(Auth::user()->designation ?? ''), 'ceo') || Auth::user()->canApproveOTFormLevel1() || Auth::user()->canApproveTimesheetHOD() || Auth::user()->canApproveTimesheetL1())
            <p class="px-3 pt-5 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Approvals</p>

            @if(Auth::user()->role === 'admin' || str_contains(strtolower(Auth::user()->designation ?? ''), 'manager') || str_contains(strtolower(Auth::user()->designation ?? ''), 'gm') || str_contains(strtolower(Auth::user()->designation ?? ''), 'ceo') || Auth::user()->canApproveOTFormLevel1())
                <a href="{{ route('approvals.ot-forms.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('approvals.ot-forms.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    OT Approvals
                </a>
            @endif

            @if(Auth::user()->canApproveTimesheetHOD() || Auth::user()->canApproveTimesheetL1() || Auth::user()->role === 'ceo' || Auth::user()->role === 'admin')
                <a href="{{ route('approvals.timesheets.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('approvals.timesheets.*') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    Timesheet Approvals
                </a>
            @endif
        @endif

        {{-- Admin Section --}}
        @if(Auth::user()->isAdmin())
            <p class="px-3 pt-5 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Administration</p>

            <button @click="adminOpen = !adminOpen"
                    class="flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                           {{ request()->routeIs('admin.*') ? 'text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Admin
                </span>
                <svg class="w-4 h-4 transition-transform" :class="adminOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                 class="ml-5 space-y-1 border-l border-gray-700 pl-3">
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                          {{ request()->routeIs('admin.users.*') ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    Users
                </a>
                <a href="{{ route('admin.settings.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                          {{ request()->routeIs('admin.settings.*') ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    Settings
                </a>
                <a href="{{ route('admin.holidays.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                          {{ request()->routeIs('admin.holidays.*') ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    Holidays
                </a>
                <a href="{{ route('admin.desknet-sync.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                          {{ request()->routeIs('admin.desknet-sync.*') ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    Desknet Sync
                </a>
                <a href="{{ route('admin.project-codes.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                          {{ request()->routeIs('admin.project-codes.*') ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    Project Codes
                </a>
            </div>
        @endif
    </nav>

    {{-- User Profile / Footer --}}
    <div class="border-t border-gray-800 px-4 py-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gray-700 rounded-full flex items-center justify-center text-sm font-medium text-white">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->designation ?? Auth::user()->email }}</p>
            </div>
            <div x-data="{ profileOpen: false }" class="relative">
                <button @click="profileOpen = !profileOpen" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
                <div x-show="profileOpen" @click.away="profileOpen = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute bottom-full right-0 mb-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-1 z-50">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</aside>
