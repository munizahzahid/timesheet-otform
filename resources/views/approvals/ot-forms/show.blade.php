<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Review OT Form — {{ $otForm->user->name }}
            </h2>
            <a href="{{ route('approvals.ot-forms.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to list</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">

            {{-- Header Info --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? 'NAME' : 'NAMA' }}:</span>
                            <span class="ml-1 text-gray-900">{{ $otForm->user->name }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? 'STAFF NO' : 'NO. KT' }}:</span>
                            <span class="ml-1 text-gray-900">{{ $otForm->user->staff_no ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? 'DEPARTMENT' : 'JABATAN' }}:</span>
                            <span class="ml-1 text-gray-900">{{ $otForm->user->department->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? 'MONTH' : 'BULAN' }}:</span>
                            <span class="ml-1 text-gray-900">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">STATUS:</span>
                            <span class="ml-1 font-semibold text-yellow-700">{{ $otForm->status_label }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">TYPE:</span>
                            <span class="ml-1 text-gray-900">{{ $otForm->form_type_label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Entries Table (read-only) --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">OT Entries</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border text-xs">
                            <thead>
                                <tr>
                                    <th class="border px-2 py-1 text-center">{{ $otForm->isExecutive() ? 'DATE' : 'TARIKH' }}</th>
                                    <th class="border px-2 py-1 text-left">{{ $otForm->isExecutive() ? 'PARTICULARS' : 'TUGAS' }}</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">Plan Start</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">Plan End</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">Plan Total</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">Actual Start</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">Actual End</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">Actual Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($otForm->entries as $entry)
                                    @php $isFilled = $entry->project_code_id || $entry->planned_start_time || $entry->actual_start_time; @endphp
                                    @if($isFilled)
                                    <tr>
                                        <td class="border px-2 py-1 text-center">
                                            {{ $otForm->isExecutive() ? $entry->entry_date->format('j/n/Y') : $entry->entry_date->day }}
                                        </td>
                                        <td class="border px-2 py-1">
                                            {{ $entry->project_code ? $entry->project_code->code : '' }}
                                            {{ $entry->project_name ? $entry->project_name : '' }}
                                        </td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center font-medium">{{ $entry->planned_total_hours > 0 ? number_format($entry->planned_total_hours, 2) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center font-medium">{{ $entry->actual_total_hours > 0 ? number_format($entry->actual_total_hours, 2) : '' }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="4" class="border px-2 py-1 text-right font-semibold">TOTAL:</td>
                                    <td class="border px-2 py-1 text-center font-bold">{{ number_format($otForm->entries->sum('planned_total_hours'), 2) }}</td>
                                    <td colspan="2" class="border"></td>
                                    <td class="border px-2 py-1 text-center font-bold">{{ number_format($otForm->entries->sum('actual_total_hours'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Approval Stamps --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Trail</h3>
                    <x-approval-stamps :stamps="$approvalStamps" />
                </div>
            </div>

            {{-- Approval Actions --}}
            @if(in_array($otForm->status, ['pending_manager', 'pending_gm']))
                @php
                    $user = auth()->user();
                    $designationLower = strtolower($user->designation ?? '');
                    $canApprove = $user->role === 'admin' ||
                                  ($otForm->status === 'pending_manager' && (str_contains($designationLower, 'manager') || str_contains($designationLower, 'asst'))) ||
                                  ($otForm->status === 'pending_gm' && (str_contains($designationLower, 'general manager') || str_contains($designationLower, 'gm') || str_contains($designationLower, 'ceo')));
                @endphp
                @if($canApprove)
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Decision</h3>
                        <div class="flex items-center gap-4">
                            <button type="button" onclick="approveForm()"
                                    class="bg-green-600 text-white px-6 py-2 rounded-md text-sm hover:bg-green-700">
                                Approve
                            </button>
                            <button type="button" onclick="rejectForm()"
                                    class="bg-red-600 text-white px-6 py-2 rounded-md text-sm hover:bg-red-700">
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800 text-sm">You are not authorized to approve this OT form based on your designation.</p>
                </div>
                @endif
            @endif
    </div>

    @push('scripts')
    <script>
        async function approveForm() {
            const signature = prompt('Type your full name to approve:');
            if (!signature) return;
            try {
                const res = await fetch('{{ route("approvals.ot-forms.approve", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ signature }),
                });
                const data = await res.json();
                console.log('Response:', data);
                if (data.success) {
                    alert(data.message || 'OT form approved!');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error: ' + err.message);
            }
        }
        async function rejectForm() {
            const remarks = prompt('Rejection reason:');
            if (!remarks) return;
            try {
                const res = await fetch('{{ route("approvals.ot-forms.reject", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ remarks }),
                });
                const data = await res.json();
                console.log('Response:', data);
                if (data.success) {
                    alert('OT form rejected.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error: ' + err.message);
            }
        }
    </script>
    @endpush
</x-app-layout>
