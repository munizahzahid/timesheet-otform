{{-- Project Module Secondary Navbar --}}
@push('sub-navbar')
<nav class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center space-x-1 h-12">
            <a href="{{ route('project.dashboard') }}"
               class="inline-flex items-center h-full px-3 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('project.dashboard') ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                Executive Dashboard
            </a>
            <a href="{{ route('project.projects.index') }}"
               class="inline-flex items-center h-full px-3 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('project.projects.*') ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                List of Project
            </a>
            <a href="{{ route('project.calendar') }}"
               class="inline-flex items-center h-full px-3 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('project.calendar') ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                Calendar
            </a>
        </div>
    </div>
</nav>
@endpush
