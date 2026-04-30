<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Timesheet Approvals') }}</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month / Year</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($timesheets as $timesheet)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $timesheet->user->name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            {{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $timesheet->user->department?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @php
                                                $badgeClass = match($timesheet->status) {
                                                    'pending_hod' => 'bg-yellow-100 text-yellow-800',
                                                    'pending_l1' => 'bg-blue-100 text-blue-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                {{ $timesheet->status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('approvals.timesheets.show', $timesheet) }}" class="text-indigo-600 hover:text-indigo-900">Review</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No timesheets pending your approval.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $timesheets->links() }}</div>
                </div>
            </div>
    </div>

    <x-help-button title="Bantuan Kelulusan Timesheet">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Kelulusan Timesheet</h3>
            <p class="mb-3">Semak dan luluskan atau tolak timesheet yang dihantar oleh ahli pasukan anda.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Cara guna</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Semak</strong> — Klik "Semak" untuk melihat butiran lengkap timesheet</li>
                <li><strong>Luluskan</strong> — Tandatang dan luluskan timesheet</li>
                <li><strong>Tolak</strong> — Tolak dengan catatan untuk staf membetulkan</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
