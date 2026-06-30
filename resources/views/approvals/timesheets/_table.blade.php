<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month / Year</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $mode === 'approved' ? 'Approved At' : 'Submitted At' }}</th>
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
                <td class="px-4 py-3 text-sm text-gray-600">
                    @if($mode === 'approved')
                        {{ $approvedAt[$timesheet->id] ?? null ? $approvedAt[$timesheet->id]->format('d/m/Y H:i') : '-' }}
                    @else
                        {{ $timesheet->submitted_at ? $timesheet->submitted_at->format('d/m/Y H:i') : '-' }}
                    @endif
                </td>
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
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    {{ $mode === 'approved' ? 'No approved timesheets found.' : 'No timesheets pending your approval.' }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
