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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
    </div>

    <x-help-button title="Bantuan Dashboard">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Gambaran Ringkas Dashboard</h3>
            <p class="mb-3">Ini adalah halaman utama anda yang menunjukkan ringkasan pantas akaun anda.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Pautan Pantas</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Timesheet</strong> — Lihat dan urus timesheet bulanan anda</li>
                <li><strong>Borang OT</strong> — Hantar dan jejak permintaan lebih masa</li>
            </ul>
            @if(Auth::user()->isAdmin())
                <h4 class="font-semibold text-gray-900 mb-1">Kad Admin</h4>
                <p>Kad di bawah menunjukkan pengguna aktif, kod projek, dan status sync Desknet terakhir. Klik mana-mana kad untuk mengurus bahagian tersebut.</p>
            @endif
        </x-slot>
    </x-help-button>
</x-app-layout>
