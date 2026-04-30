<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Project Codes') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Filters --}}
                    <form method="GET" action="{{ route('admin.project-codes.index') }}" class="mb-6 flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Code, name, client, manager..."
                                   class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">All</option>
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">All</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Filter</button>
                            <a href="{{ route('admin.project-codes.index') }}" class="ml-2 text-sm text-gray-600 hover:text-gray-900">Reset</a>
                        </div>
                    </form>

                    <p class="text-sm text-gray-500 mb-4">
                        Showing {{ $projectCodes->total() }} project codes (read-only, synced from Desknet)
                    </p>

                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($projectCodes as $pc)
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ $pc->code }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-900 max-w-xs truncate">{{ $pc->name }}</td>
                                        <td class="px-3 py-3 text-sm">
                                            @if($pc->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-3 py-8 text-center text-gray-500">No project codes found. Run a Desknet sync first.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $projectCodes->links() }}</div>
                </div>
            </div>
    </div>

    <x-help-button title="Bantuan Kod Projek">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Kod Projek</h3>
            <p class="mb-3">Lihat semua kod projek yang disinkronkan dari Desknet. Ini adalah bacaan sahaja dan dikemaskini melalui Sinkronisasi Desknet.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Ciri-ciri</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Cari</strong> — Tapis mengikut kod, nama, pelanggan, atau pengurus</li>
                <li><strong>Tahun</strong> — Tapis mengikut tahun projek</li>
                <li><strong>Status</strong> — Tunjukkan projek aktif atau tidak aktif</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
