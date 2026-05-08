<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Review Timesheet — {{ $timesheet->user->name }}
            </h2>
            <a href="{{ route('approvals.timesheets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to list</a>
        </div>
    </x-slot>

    <div class="max-w-full mx-auto">

            {{-- Header Info --}}
            @include('timesheets.partials._header')

            {{-- Matrix Container (Read-only) --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="border-collapse text-xs" style="min-width: 100%;">
                        <thead>
                            {{-- Day number row --}}
                            <tr class="bg-gray-100">
                                <th class="sticky left-0 z-20 bg-gray-100 border border-gray-300 px-2 py-1 text-left min-w-[180px]">ITEM</th>
                                <th class="sticky left-[180px] z-20 bg-gray-100 border border-gray-300 px-1 py-1 min-w-[50px]">TYPE</th>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $day = $days[$d]; @endphp
                                    <th class="border border-gray-300 px-1 py-1 text-center min-w-[38px]
                                        {{ $day['day_type'] === 'off_day' ? 'bg-yellow-100' : '' }}
                                        {{ $day['day_type'] === 'public_holiday' ? 'bg-red-100' : '' }}
                                        {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-100' : '' }}">
                                        {{ $d }}
                                    </th>
                                @endfor
                                <th class="border border-gray-300 px-2 py-1 text-center min-w-[50px] bg-gray-200 font-bold">TOTAL</th>
                            </tr>
                            {{-- Day of week row --}}
                            <tr class="bg-gray-50">
                                <th class="sticky left-0 z-20 bg-gray-50 border border-gray-300 px-2 py-1"></th>
                                <th class="sticky left-[180px] z-20 bg-gray-50 border border-gray-300 px-1 py-1"></th>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $day = $days[$d]; @endphp
                                    <th class="border border-gray-300 px-1 py-0.5 text-center text-[10px] font-normal
                                        {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                        {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}">
                                        {{ $day['day_of_week'] }}
                                    </th>
                                @endfor
                                <th class="border border-gray-300 px-2 py-1 bg-gray-200"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- ===== UPPER TABLE: Admin Job Rows ===== --}}
                            @foreach($adminTypes as $type => $label)
                                <tr class="{{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">
                                    <td class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-1 font-medium text-[11px] whitespace-nowrap {{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">
                                        {{ $loop->iteration }}. {{ $label }}
                                    </td>
                                    <td class="sticky left-[180px] z-10 bg-white border border-gray-300 px-1 py-1 text-center text-[10px] text-gray-400 {{ $loop->index < 3 ? 'bg-blue-50/30' : '' }}">hrs</td>
                                    @for($d = 1; $d <= $daysInMonth; $d++)
                                        @php $day = $days[$d]; @endphp
                                        <td class="border border-gray-300 p-0 text-center
                                            {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                            {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                            {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                            <span class="text-xs">{{ $adminHours[$type][$d] ?? '' }}</span>
                                        </td>
                                    @endfor
                                    <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50">
                                        @php
                                            $total = 0;
                                            foreach (($adminHours[$type] ?? []) as $h) {
                                                $total += is_numeric($h) ? (float)$h : 0;
                                            }
                                            echo $total > 0 ? number_format($total, 1) : '';
                                        @endphp
                                    </td>
                                </tr>
                            @endforeach

                            {{-- TOTAL ADMIN JOB row --}}
                            <tr class="bg-gray-200 font-bold">
                                <td class="sticky left-0 z-10 bg-gray-200 border border-gray-300 px-2 py-1 text-[11px]">TOTAL ADMIN JOB</td>
                                <td class="sticky left-[180px] z-10 bg-gray-200 border border-gray-300 px-1 py-1"></td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                        @php
                                            $dayTotal = 0;
                                            foreach ($adminHours as $type => $hours) {
                                                $dayTotal += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                            }
                                            echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                        @endphp
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-gray-300">
                                    @php
                                        $grandTotal = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            foreach ($hours as $h) {
                                                $grandTotal += is_numeric($h) ? (float)$h : 0;
                                            }
                                        }
                                        echo number_format($grandTotal, 1);
                                    @endphp
                                </td>
                            </tr>

                            {{-- Spacer --}}
                            <tr><td :colspan="{{ $daysInMonth + 3 }}" class="h-2 bg-gray-100 border-0"></td></tr>

                            {{-- ===== LOWER TABLE: Project Rows (flat iteration) ===== --}}
                            @foreach($flatProjectRows as $fRow)
                                <tr class="{{ $fRow['sIdx'] === 0 ? 'border-t-2 border-gray-400' : '' }}">
                                    {{-- Project label cell: content only on first sub-row --}}
                                    <td class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-1 align-top min-w-[180px] {{ $fRow['sIdx'] === 0 ? '' : 'border-t-0' }}">
                                        @if($fRow['sIdx'] === 0)
                                            <div>
                                                <div class="flex items-center gap-1 mb-1">
                                                    <span class="font-bold text-[11px]">#{{ $fRow['pIdx'] + 1 }}</span>
                                                </div>
                                                <div class="text-[10px] font-medium">{{ $fRow['project_name'] }}</div>
                                                <div class="text-[9px] text-gray-400 mt-0.5 truncate">{{ $fRow['project_code'] }}</div>
                                            </div>
                                        @endif
                                    </td>
                                    {{-- Sub-row type label --}}
                                    <td class="sticky left-[180px] z-10 bg-white border border-gray-300 px-1 py-0.5 text-center text-[9px] font-medium whitespace-nowrap">
                                        {{ $fRow['label'] }}
                                    </td>
                                    {{-- Day cells --}}
                                    @for($d = 1; $d <= $daysInMonth; $d++)
                                        @php $day = $days[$d]; @endphp
                                        <td class="border border-gray-300 p-0 text-center
                                            {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                            {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                            {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                            <span class="text-xs">
                                                @php
                                                    $val = $projectRowsData[$fRow['pIdx']]['hours'][$d][$fRow['field']] ?? 0;
                                                    echo $val > 0 ? number_format($val, 1) : '';
                                                @endphp
                                            </span>
                                        </td>
                                    @endfor
                                    {{-- Row total --}}
                                    <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50 text-xs">
                                        @php
                                            $total = 0;
                                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                                $total += $projectRowsData[$fRow['pIdx']]['hours'][$d][$fRow['field']] ?? 0;
                                            }
                                            echo $total > 0 ? number_format($total, 1) : '';
                                        @endphp
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Spacer --}}
                            <tr><td :colspan="{{ $daysInMonth + 3 }}" class="h-2 bg-gray-100 border-0"></td></tr>

                            {{-- ===== SUMMARY ROWS ===== --}}
                            {{-- TOTAL EXTERNAL PROJECT --}}
                            <tr class="bg-sky-100 font-bold">
                                <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">TOTAL EXTERNAL PROJECT</td>
                                <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                        @php
                                            $dayTotal = 0;
                                            foreach ($projectRowsData as $project) {
                                                $dayTotal += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0);
                                            }
                                            echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                        @endphp
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200">
                                    @php
                                        $grandTotal = 0;
                                        foreach ($projectRowsData as $project) {
                                            foreach ($project['hours'] as $dayHours) {
                                                $grandTotal += ($dayHours['normal_nc'] ?? 0) + ($dayHours['normal_cobq'] ?? 0);
                                            }
                                        }
                                        echo number_format($grandTotal, 1);
                                    @endphp
                                </td>
                            </tr>

                            {{-- TOTAL WORKING HOURS --}}
                            <tr class="bg-green-100 font-bold">
                                <td class="sticky left-0 z-10 bg-green-100 border border-gray-300 px-2 py-1 text-[11px]">TOTAL WORKING HOURS</td>
                                <td class="sticky left-[180px] z-10 bg-green-100 border border-gray-300 px-1 py-1"></td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                        @php
                                            $dayTotal = 0;
                                            foreach ($adminHours as $type => $hours) {
                                                $dayTotal += is_numeric(($hours[$d] ?? 0)) ? (float)($hours[$d] ?? 0) : 0;
                                            }
                                            foreach ($projectRowsData as $project) {
                                                $dayTotal += ($project['hours'][$d]['normal_nc'] ?? 0) + ($project['hours'][$d]['normal_cobq'] ?? 0) + ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                                            }
                                            echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                        @endphp
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-green-200">
                                    @php
                                        $grandTotal = 0;
                                        foreach ($adminHours as $type => $hours) {
                                            foreach ($hours as $h) {
                                                $grandTotal += is_numeric($h) ? (float)$h : 0;
                                            }
                                        }
                                        foreach ($projectRowsData as $project) {
                                            foreach ($project['hours'] as $dayHours) {
                                                $grandTotal += ($dayHours['normal_nc'] ?? 0) + ($dayHours['normal_cobq'] ?? 0) + ($dayHours['ot_nc'] ?? 0) + ($dayHours['ot_cobq'] ?? 0);
                                            }
                                        }
                                        echo number_format($grandTotal, 1);
                                    @endphp
                                </td>
                            </tr>

                            {{-- HOURS AVAILABLE --}}
                            <tr class="bg-gray-100 font-semibold">
                                <td class="sticky left-0 z-10 bg-gray-100 border border-gray-300 px-2 py-1 text-[11px]">HOURS AVAILABLE</td>
                                <td class="sticky left-[180px] z-10 bg-gray-100 border border-gray-300 px-1 py-1"></td>
                                @php $totalAvail = 0; @endphp
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $avail = $days[$d]['available_hours']; $totalAvail += $avail; @endphp
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs
                                        {{ $days[$d]['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                        {{ $days[$d]['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                        {{ in_array($days[$d]['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                        {{ $avail > 0 ? $avail : '' }}
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-gray-200">{{ $totalAvail }}</td>
                            </tr>

                            {{-- OVERTIME --}}
                            <tr class="bg-sky-100 font-bold">
                                <td class="sticky left-0 z-10 bg-sky-100 border border-gray-300 px-2 py-1 text-[11px]">OVERTIME</td>
                                <td class="sticky left-[180px] z-10 bg-sky-100 border border-gray-300 px-1 py-1"></td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs">
                                        @php
                                            $dayTotal = 0;
                                            foreach ($projectRowsData as $project) {
                                                $dayTotal += ($project['hours'][$d]['ot_nc'] ?? 0) + ($project['hours'][$d]['ot_cobq'] ?? 0);
                                            }
                                            echo $dayTotal > 0 ? number_format($dayTotal, 1) : '';
                                        @endphp
                                    </td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-sky-200">
                                    @php
                                        $grandTotal = 0;
                                        foreach ($projectRowsData as $project) {
                                            foreach ($project['hours'] as $dayHours) {
                                                $grandTotal += ($dayHours['ot_nc'] ?? 0) + ($dayHours['ot_cobq'] ?? 0);
                                            }
                                        }
                                        echo number_format($grandTotal, 1);
                                    @endphp
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Export Options --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Options</h3>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('timesheets.export-excel', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                            Download Excel
                        </a>
                        <a href="{{ route('timesheets.export-pdf', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            Download PDF
                        </a>
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
            @if(in_array($timesheet->status, ['pending_hod', 'pending_l1']))
                @php
                    $user = auth()->user();
                    $canApprove = $user->role === 'admin' ||
                                  ($timesheet->status === 'pending_hod' && $user->canApproveTimesheetHOD()) ||
                                  ($timesheet->status === 'pending_l1' && $user->canApproveTimesheetL1());
                @endphp
                @if($canApprove)
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approval Decision</h3>
                        <div class="flex items-center gap-4">
                            @if($timesheet->status === 'pending_hod')
                                <button type="button" onclick="approveHOD()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                                    Approve (HOD)
                                </button>
                                <button type="button" onclick="rejectHOD()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                                    Reject (HOD)
                                </button>
                            @elseif($timesheet->status === 'pending_l1')
                                <button type="button" onclick="approveL1()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                                    Approve (Asst Mgr/Mngr)
                                </button>
                                <button type="button" onclick="rejectL1()"
                                        class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                                    Reject (Asst Mgr/Mngr)
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800 text-sm">You are not authorized to approve this timesheet based on your role.</p>
                </div>
                @endif
            @endif

            {{-- Admin Delete Action --}}
            @if(auth()->user()->isAdmin())
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin Actions</h3>
                        <button onclick="showDeleteModal({{ $timesheet->id }}, 'timesheet', '{{ $timesheet->status }}')"
                                class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            Delete This Timesheet (Admin)
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
        // Helper function to get signature with auto-completion
        function getSignature() {
            const fullName = '{{ Auth::user()->name }}';
            // Extract suffix (everything after BIN/BINTI/B/BT)
            const suffixMatch = fullName.match(/\s+(BIN|BINTI|B|BT)\s+.+/i);
            const prefix = suffixMatch ? fullName.substring(0, suffixMatch.index).trim() : fullName;

            const signature = prompt(`Type your name to approve:\n\nYour full name: ${fullName}\n\nYou only need to type: ${prefix}\n\nType your name:`);
            if (!signature) return null;
            // Auto-complete if user only typed the prefix
            return signature.trim() === prefix ? fullName : signature.trim();
        }

        async function approveHOD() {
            const signature = getSignature();
            if (!signature) return;
            try {
                const res = await fetch('{{ route("timesheets.approve-hod", $timesheet) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ signature }),
                });
                const data = await res.json();
                if (data.success) {
                    alert('Timesheet approved!');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
        async function rejectHOD() {
            const remarks = prompt('Rejection reason:');
            if (!remarks) return;
            try {
                const res = await fetch('{{ route("timesheets.reject-hod", $timesheet) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ remarks }),
                });
                const data = await res.json();
                if (data.success) {
                    alert('Timesheet rejected.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
        async function approveL1() {
            const signature = getSignature();
            if (!signature) return;
            try {
                const res = await fetch('{{ route("timesheets.approve-l1", $timesheet) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ signature }),
                });
                const data = await res.json();
                if (data.success) {
                    alert('Timesheet approved!');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
        async function rejectL1() {
            const remarks = prompt('Rejection reason:');
            if (!remarks) return;
            try {
                const res = await fetch('{{ route("timesheets.reject-l1", $timesheet) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ remarks }),
                });
                const data = await res.json();
                if (data.success) {
                    alert('Timesheet rejected.');
                    location.reload();
                } else {
                    alert(data.error || 'Failed.');
                }
            } catch (err) {
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
            if (deleteType === 'timesheet') {
                actionUrl = '{{ route('timesheets.destroy', $timesheet) }}';
            } else {
                actionUrl = '{{ route('ot-forms.destroy', ['otForm' => 0]) }}'.replace('/0', '/' + deleteId);
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
