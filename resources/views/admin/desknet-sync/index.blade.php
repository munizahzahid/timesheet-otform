<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Desknet Sync Dashboard') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded whitespace-pre-wrap break-words">{{ session('error') }}</div>
            @endif

            {{-- Sync Status Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {{-- Staff Sync --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Staff List Sync</h3>
                    @if($lastStaffSync)
                        <p class="text-sm text-gray-500">Last successful: {{ $lastStaffSync->completed_at->format('d M Y H:i') }}</p>
                        <p class="text-sm text-gray-500">
                            Created: {{ $lastStaffSync->records_created }} |
                            Updated: {{ $lastStaffSync->records_updated }} |
                            Deactivated: {{ $lastStaffSync->records_deactivated }}
                        </p>
                    @else
                        <p class="text-sm text-gray-400">Never synced</p>
                    @endif
                    <form method="POST" action="{{ route('admin.desknet-sync.run') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="type" value="staff">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700"
                                onclick="this.disabled=true; this.innerText='Syncing...'; this.form.submit();">
                            Sync Staff Now
                        </button>
                    </form>
                </div>

                {{-- Project Codes Sync --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Project Codes Sync</h3>
                    @if($lastProjectSync)
                        <p class="text-sm text-gray-500">Last successful: {{ $lastProjectSync->completed_at->format('d M Y H:i') }}</p>
                        <p class="text-sm text-gray-500">
                            Created: {{ $lastProjectSync->records_created }} |
                            Updated: {{ $lastProjectSync->records_updated }} |
                            Deactivated: {{ $lastProjectSync->records_deactivated }}
                        </p>
                    @else
                        <p class="text-sm text-gray-400">Never synced</p>
                    @endif
                    <form method="POST" action="{{ route('admin.desknet-sync.run') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="type" value="project_codes">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700"
                                onclick="this.disabled=true; this.innerText='Syncing...'; this.form.submit();">
                            Sync Project Codes Now
                        </button>
                    </form>
                </div>
            </div>

            {{-- Sync All + Test --}}
            <div class="mb-6 flex gap-3">
                <form method="POST" action="{{ route('admin.desknet-sync.run') }}">
                    @csrf
                    <input type="hidden" name="type" value="all">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md text-sm hover:bg-indigo-700"
                            onclick="this.disabled=true; this.innerText='Running full sync...'; this.form.submit();">
                        Sync All Now
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.desknet-sync.test') }}">
                    @csrf
                    <button type="submit" class="bg-gray-600 text-white px-6 py-2 rounded-md text-sm hover:bg-gray-700"
                            onclick="this.disabled=true; this.innerText='Testing...'; this.form.submit();">
                        Test Connection
                    </button>
                </form>
            </div>

            {{-- Sync Log History --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Sync History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trigger</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deactivated</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $log->started_at->format('d M Y H:i:s') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $log->sync_type === 'staff' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                                {{ str_replace('_', ' ', ucfirst($log->sync_type)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            {{ ucfirst($log->trigger_type) }}
                                            @if($log->triggeredBy)
                                                <span class="text-xs">({{ $log->triggeredBy->name }})</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($log->status === 'success')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Success</span>
                                            @elseif($log->status === 'running')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Running</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $log->records_created }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $log->records_updated }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $log->records_deactivated }}</td>
                                        <td class="px-4 py-3 text-sm text-red-500 max-w-xs truncate">{{ $log->error_message ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">No sync history yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $logs->links() }}</div>
                </div>
            </div>
    </div>

    <x-help-button title="Desknet Sync Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Desknet Sync Dashboard</h3>
            <p class="mb-3">Sync staff data and project codes from Desknet into the system.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Actions</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Sync Staff</strong> — Import/update staff info (name, department, designation)</li>
                <li><strong>Sync Project Codes</strong> — Import/update project codes from Desknet</li>
                <li><strong>Sync All</strong> — Run both syncs at once</li>
                <li><strong>Test Connection</strong> — Verify the Desknet API connection is working</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
