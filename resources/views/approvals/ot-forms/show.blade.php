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
                                    class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                                Approve
                            </button>
                            <button type="button" onclick="rejectForm()"
                                    class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
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

            {{-- Admin Delete Action --}}
            @if(auth()->user()->isAdmin())
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin Actions</h3>
                        <button onclick="showDeleteModal({{ $otForm->id }}, 'ot-form', '{{ $otForm->status }}')"
                                class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            Delete This OT Form (Admin)
                        </button>
                    </div>
                </div>
            @endif
    </div>

    {{-- Delete Reason Modal --}}
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Deletion</h3>
                <p class="text-sm text-gray-600 mb-4">
                    You are about to delete a <span id="deleteType" class="font-medium"></span> with status:
                    <span id="deleteStatus" class="font-medium text-red-600"></span>
                </p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for deletion (required):</label>
                    <textarea id="deleteReason" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Please provide a reason..."></textarea>
                    <p id="deleteReasonError" class="text-red-600 text-xs mt-1 hidden">Reason is required</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancel</button>
                    <button onclick="confirmDelete()" class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-md">Delete</button>
                </div>
            </div>
        </div>
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

        let deleteId = null;
        let deleteType = null;

        function showDeleteModal(id, type, status) {
            deleteId = id;
            deleteType = type;
            document.getElementById('deleteType').textContent = type === 'timesheet' ? 'timesheet' : 'OT form';
            document.getElementById('deleteStatus').textContent = status;
            document.getElementById('deleteReason').value = '';
            document.getElementById('deleteReasonError').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
            deleteId = null;
            deleteType = null;
        }

        function confirmDelete() {
            const reason = document.getElementById('deleteReason').value.trim();
            if (!reason) {
                document.getElementById('deleteReasonError').classList.remove('hidden');
                return;
            }

            let actionUrl;
            if (deleteType === 'ot-form') {
                actionUrl = '{{ route('ot-forms.destroy', $otForm) }}';
            } else {
                actionUrl = '{{ route('timesheets.destroy', ['timesheet' => 0]) }}'.replace('/0', '/' + deleteId);
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';

            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'delete_reason';
            reasonInput.value = reason;

            form.appendChild(csrfInput);
            form.appendChild(methodInput);
            form.appendChild(reasonInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    @endpush
</x-app-layout>
