<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('OT Form Approvals') }}</h2>
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
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
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ot->company_name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @php
                                                $badgeClass = match($ot->status) {
                                                    'pending_hod' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
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
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No OT forms pending your approval.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $otForms->links() }}</div>
                </div>
            </div>
    </div>

    <x-help-button title="OT Approvals Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">OT Form Approvals</h3>
            <p class="mb-3">Review and approve or reject OT forms submitted by your team members.</p>
            <h4 class="font-semibold text-gray-900 mb-1">How to use</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Review</strong> — Click "Review" to see the full OT form details</li>
                <li><strong>Approve</strong> — Sign and approve the OT form</li>
                <li><strong>Reject</strong> — Reject with remarks for the staff to correct</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
