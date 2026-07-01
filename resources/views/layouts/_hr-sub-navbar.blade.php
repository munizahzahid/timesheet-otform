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
        </div>
    </div>
</nav>
