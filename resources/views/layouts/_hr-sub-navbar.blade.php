<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex space-x-8 h-10" aria-label="HR Sections">
            <a href="{{ route('timesheets.index') }}"
               class="inline-flex items-center gap-2 px-1 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('timesheets.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                <span>Timesheet</span>
                @if($newTimesheetCount > 0)
                    <span class="inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white" style="width: 20px; height: 20px;">
                        {{ $newTimesheetCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('ot-forms.index') }}"
               class="inline-flex items-center gap-2 px-1 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('ot-forms.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                <span>OT Form</span>
                @if($newOtFormCount > 0)
                    <span class="inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white" style="width: 20px; height: 20px;">
                        {{ $newOtFormCount }}
                    </span>
                @endif
            </a>

            @if(Auth::user()->canViewAllRecords())
                <div class="relative inline-flex items-center h-10" x-data="{ open: false }" @click.away="open = false">
                    <button @click="open = !open" type="button"
                            class="inline-flex items-center gap-1 px-1 text-sm font-medium border-b-2 transition-colors focus:outline-none
                                   {{ request()->routeIs('records.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                        <span>All Records</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute top-full left-0 mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg z-30 overflow-hidden">
                        <a href="{{ route('records.timesheets') }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('records.timesheets') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                            Timesheet
                        </a>
                        <a href="{{ route('records.timesheets.summary') }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('records.timesheets.summary') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                            Monthly Summary
                        </a>
                        <a href="{{ route('records.ot-forms') }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('records.ot-forms.*') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                            OT Form
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</nav>
