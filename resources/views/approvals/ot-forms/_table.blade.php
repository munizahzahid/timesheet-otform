<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month / Year</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $mode === 'approved' ? 'Approved At' : 'Submitted At' }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        @forelse($otForms as $ot)
            <tr>
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $ot->user->name }}</td>
                <td class="px-4 py-3 text-sm">
                    {{ DateTime::createFromFormat('!m', $ot->month)->format('F') }} {{ $ot->year }}
                </td>
                <td class="px-4 py-3 text-sm">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ot->form_type === 'executive' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                        {{ $ot->form_type === 'executive' ? 'Executive' : 'Non-Executive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    @if($mode === 'approved')
                        {{ $approvedAt[$ot->id] ?? null ? $approvedAt[$ot->id]->format('d/m/Y H:i') : '-' }}
                    @else
                        {{ $ot->plan_submitted_at ? $ot->plan_submitted_at->format('d/m/Y H:i') : '-' }}
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">
                    @php
                        $badgeClass = match($ot->status) {
                            'pending_manager' => 'bg-yellow-100 text-yellow-800',
                            'pending_hr' => 'bg-cyan-100 text-cyan-800',
                            'pending_gm' => 'bg-blue-100 text-blue-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            'returned_hr' => 'bg-orange-100 text-orange-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                        {{ $ot->status_label }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm">
                    <a href="{{ route('approvals.ot-forms.show', $ot) }}" class="text-indigo-600 hover:text-indigo-900">Review</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    {{ $mode === 'approved' ? 'No approved OT forms found.' : 'No OT forms pending your approval.' }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
