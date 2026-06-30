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
                        @php
                            $statusColor = match($otForm->status) {
                                'pending_manager' => 'text-yellow-700',
                                'pending_hr' => 'text-cyan-700',
                                'pending_gm' => 'text-blue-700',
                                'approved' => 'text-green-700',
                                'rejected' => 'text-red-700',
                                'returned_hr' => 'text-orange-700',
                                default => 'text-gray-700',
                            };
                        @endphp
                        <span class="ml-1 font-semibold {{ $statusColor }}">{{ $otForm->status_label }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">TYPE:</span>
                        <span class="ml-1 text-gray-900">{{ $otForm->form_type_label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">

                {{-- Entries Table --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">OT Entries</h3>
                            @if($otForm->status === 'pending_hr' && auth()->user()->canReviewOTForm())
                                <div class="flex items-center gap-2">
                                    <button type="button" id="btnEditMode" onclick="toggleEditMode()"
                                            class="px-3 py-1.5 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #2563eb !important; color: white !important;">
                                        Edit
                                    </button>
                                    <button type="button" id="btnOriginalView" onclick="toggleOriginalView()"
                                            class="px-3 py-1.5 rounded-md text-sm hover:shadow-md transition-all {{ $hasHrCorrections ? '' : 'hidden' }}" style="background-color: #6b7280 !important; color: white !important;">
                                        View Original
                                    </button>
                                    <button type="button" id="btnCurrentView" onclick="toggleOriginalView()"
                                            class="px-3 py-1.5 rounded-md text-sm hover:shadow-md transition-all hidden" style="background-color: #6b7280 !important; color: white !important;">
                                        View Current
                                    </button>
                                    <button type="button" id="btnSaveEdits" onclick="saveHrEdits()"
                                            class="px-3 py-1.5 rounded-md text-sm hover:shadow-md transition-all hidden" style="background-color: #16a34a !important; color: white !important;">
                                        Save Changes
                                    </button>
                                    <button type="button" id="btnCancelEdit" onclick="toggleEditMode()"
                                            class="px-3 py-1.5 rounded-md text-sm hover:shadow-md transition-all hidden" style="background-color: #dc2626 !important; color: white !important;">
                                        Cancel
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div id="currentFormView" class="overflow-x-auto">
                            @include('approvals.ot-forms._entries_table', ['editMode' => false])
                        </div>

                        <div id="originalFormView" class="overflow-x-auto hidden">
                            @include('approvals.ot-forms._original_entries_table')
                        </div>

                        <div id="editFormView" class="overflow-x-auto hidden">
                            @include('approvals.ot-forms._entries_table', ['editMode' => true])
                        </div>
                    </div>
                </div>

                {{-- HR Correction Notes --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">HR Correction Notes</h3>
                        @if($otForm->hr_remarks)
                            <div class="space-y-2">
                                <p class="text-xs text-gray-500">
                                    Edited by {{ $otForm->hrEditor?->name ?? 'HR' }} on {{ $otForm->hr_edited_at?->format('d/m/Y H:i') }}
                                </p>
                                <div class="text-sm text-gray-800 bg-yellow-50 border border-yellow-200 rounded-md p-3 space-y-3 leading-relaxed">
                                    @foreach(explode("\n\n", $otForm->hr_remarks) as $dateBlock)
                                        <div>{!! nl2br(e($dateBlock)) !!}</div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No HR corrections recorded.</p>
                        @endif
                    </div>
                </div>

                {{-- Approval Stamps --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Trail</h3>
                        <x-approval-stamps :stamps="$approvalStamps" />
                    </div>
                </div>

                {{-- Export Options --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Options</h3>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('ot-forms.export-excel', $otForm) }}"
                               class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                                Download Excel
                            </a>
                            <a href="{{ route('ot-forms.export-pdf', $otForm) }}"
                               class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                                Download PDF
                            </a>
                        </div>
                    </div>
                </div>

                {{-- HR Review Actions --}}
                @if($otForm->status === 'pending_hr' && auth()->user()->canReviewOTForm())
                    <div id="hrReviewActions" class="bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">HR Review</h3>
                            <p class="text-sm text-gray-600 mb-4">This OT form has been approved by Manager/HOD and is awaiting your review before forwarding to CEO.</p>
                            <div class="flex items-center gap-4">
                                <button type="button" onclick="hrForwardForm()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                                    Approve & Forward to CEO
                                </button>
                                <button type="button" onclick="hrReturnForm()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #f59e0b !important; color: white !important;">
                                    Return for Correction
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Approval Actions (Manager/HOD and CEO) --}}
                @if(in_array($otForm->status, ['pending_manager', 'pending_gm']))
                    @php
                        $user = auth()->user();
                        $designationLower = strtolower($user->designation ?? '');
                        $canApprove = $user->role === 'admin' ||
                                      ($otForm->status === 'pending_manager' && (str_contains($designationLower, 'manager') || str_contains($designationLower, 'asst'))) ||
                                      ($otForm->status === 'pending_gm' && (str_contains($designationLower, 'general manager') || str_contains($designationLower, 'gm') || str_contains($designationLower, 'ceo')));
                    @endphp
                    @if($canApprove)
                    <div class="bg-white shadow-sm sm:rounded-lg">
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
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm">You are not authorized to approve this OT form based on your designation.</p>
                    </div>
                    @endif
                @endif

                {{-- Admin Delete Action --}}
                @if(auth()->user()->isAdmin())
                    <div class="bg-white shadow-sm sm:rounded-lg">
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
        let editMode = false;
        let originalView = false;
        const hasHrCorrections = {{ $hasHrCorrections ? 'true' : 'false' }};

        function toggleEditMode() {
            editMode = !editMode;
            document.getElementById('btnEditMode').classList.toggle('hidden', editMode);
            document.getElementById('btnSaveEdits').classList.toggle('hidden', !editMode);
            document.getElementById('btnCancelEdit').classList.toggle('hidden', !editMode);
            document.getElementById('btnOriginalView').classList.toggle('hidden', editMode || originalView || !hasHrCorrections);
            document.getElementById('btnCurrentView').classList.toggle('hidden', true);

            document.getElementById('currentFormView').classList.toggle('hidden', editMode);
            document.getElementById('editFormView').classList.toggle('hidden', !editMode);
            document.getElementById('originalFormView').classList.add('hidden');
            originalView = false;

            const hrReviewActions = document.getElementById('hrReviewActions');
            if (hrReviewActions) hrReviewActions.classList.toggle('hidden', editMode);
        }

        function toggleOriginalView() {
            originalView = !originalView;
            document.getElementById('btnOriginalView').classList.toggle('hidden', originalView);
            document.getElementById('btnCurrentView').classList.toggle('hidden', !originalView);
            document.getElementById('btnEditMode').classList.toggle('hidden', originalView);

            document.getElementById('currentFormView').classList.toggle('hidden', originalView);
            document.getElementById('originalFormView').classList.toggle('hidden', !originalView);
        }

        function collectEntriesData() {
            const rows = document.querySelectorAll('#editFormView tbody tr');
            const entries = {};
            rows.forEach(row => {
                const entryId = row.getAttribute('data-entry-id');
                if (!entryId) return;

                const getValue = (selector) => {
                    const el = row.querySelector(selector);
                    return el ? el.value : null;
                };

                entries[entryId] = {
                    id: entryId,
                    project_code_id: getValue('.entry-project-code'),
                    project_name: getValue('.entry-project-name'),
                    planned_start_time: getValue('.entry-planned-start'),
                    planned_end_time: getValue('.entry-planned-end'),
                    actual_start_time: getValue('.entry-actual-start'),
                    actual_end_time: getValue('.entry-actual-end'),
                };
            });
            return entries;
        }

        async function saveHrEdits() {
            const entries = collectEntriesData();
            if (!confirm('Save HR corrections?')) return;

            try {
                const res = await fetch('{{ route("approvals.ot-forms.hr-edit", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ entries }),
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'Corrections saved.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed to save corrections.');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error: ' + err.message);
            }
        }

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

        async function hrForwardForm() {
            if (!confirm('Forward this OT form to CEO for final approval?')) return;
            try {
                const res = await fetch('{{ route("approvals.ot-forms.hr-forward", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                console.log('HR Forward Response:', data);
                if (data.success) {
                    alert(data.message || 'Forwarded to CEO.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                console.error('HR Forward Error:', err);
                alert('Error: ' + err.message);
            }
        }

        async function hrReturnForm() {
            const remarks = prompt('Reason for returning this OT form:');
            if (!remarks) return;
            try {
                const res = await fetch('{{ route("approvals.ot-forms.hr-return", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ remarks }),
                });
                const data = await res.json();
                console.log('HR Return Response:', data);
                if (data.success) {
                    alert(data.message || 'Returned to employee.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                console.error('HR Return Error:', err);
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

            // Add user_id if viewing another user's history
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('user_id')) {
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = urlParams.get('user_id');
                form.appendChild(userIdInput);
            }

            form.appendChild(csrfInput);
            form.appendChild(methodInput);
            form.appendChild(reasonInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    @endpush
</x-app-layout>
