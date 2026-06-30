{{-- Project Module Secondary Navbar --}}
@push('sub-navbar')
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex space-x-8 h-10">
            <a href="{{ route('admin.project.dashboard') }}"
               class="inline-flex items-center px-1 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('admin.project.dashboard') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                Executive Dashboard
            </a>
            <a href="{{ route('admin.project.projects.index') }}"
               class="inline-flex items-center px-1 text-sm font-medium border-b-2 transition-colors
                      {{ request()->routeIs('admin.project.projects.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                List of Project
            </a>
        </div>
    </div>
</nav>
@endpush
