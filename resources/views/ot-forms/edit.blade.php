<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $otForm->isExecutive() ? 'OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF' : 'BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)' }}
            </h2>
            @php
                $badgeClass = match($otForm->status) {
                    'draft' => 'bg-gray-100 text-gray-800',
                    'pending_manager' => 'bg-yellow-100 text-yellow-800',
                    'pending_gm' => 'bg-blue-100 text-blue-800',
                    'approved' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    default => 'bg-gray-100 text-gray-800',
                };
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                {{ $otForm->status_label }}
            </span>
        </div>
    </x-slot>

    @push('styles')
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 0.3cm;
            }
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                font-family: 'Times New Roman', Times, serif;
            }
            .no-print { display: none !important; }
        }
    </style>
    @endpush

    <div class="max-w-7xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded no-print">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded no-print">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('ot-forms.save', $otForm) }}" id="otForm">
                @csrf
                @method('PUT')

                <div id="printArea" class="bg-white shadow-sm sm:rounded-lg p-6">
                    @if($otForm->isExecutive())
                        {{-- Executive Form --}}

                        {{-- Header --}}
                        <div class="flex justify-between items-start border-b-2 border-gray-800 pb-3 mb-4">
                            <div class="text-center">
                                <h1 class="font-bold text-2xl tracking-wide">INGRESS</h1>
                                <p class="text-sm text-gray-600 font-semibold">GROUP OF COMPANIES</p>
                            </div>
                            <div class="border border-gray-400 rounded text-xs w-72">
                                <div class="flex border-b border-gray-300 px-3 py-1.5">
                                    <span class="w-24 font-semibold text-gray-600">Department</span>
                                    <span class="flex-1">{{ $otForm->user->department->name ?? '-' }}</span>
                                </div>
                                <div class="flex border-b border-gray-300 px-3 py-1.5">
                                    <span class="w-24 font-semibold text-gray-600">Doc No</span>
                                    <span class="flex-1">OCF-F-01</span>
                                    <span class="w-20 font-semibold text-gray-600">Issue No</span>
                                    <span>01</span>
                                </div>
                                <div class="flex px-3 py-1.5">
                                    <span class="w-24 font-semibold text-gray-600">Page</span>
                                    <span class="flex-1">1 of 1</span>
                                    <span class="w-20 font-semibold text-gray-600">Rev No</span>
                                    <span>00</span>
                                </div>
                            </div>
                        </div>

                        {{-- Title Row --}}
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="font-bold text-base">OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF</h2>
                            <div class="text-sm text-gray-600">Serial No: <span class="border-b border-gray-400 inline-block w-32"></span></div>
                        </div>

                        {{-- Staff Info --}}
                        <div class="grid grid-cols-3 gap-x-6 gap-y-3 mb-5 text-sm border border-gray-300 rounded p-4 bg-gray-50">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Name :</span>
                                <span class="border-b border-gray-400 flex-1 py-0.5 px-1">{{ $otForm->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Department :</span>
                                <span class="border-b border-gray-400 flex-1 py-0.5 px-1">{{ $otForm->user->department->name ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Month :</span>
                                <span class="border-b border-gray-400 flex-1 py-0.5 px-1">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Staff No :</span>
                                <span class="border-b border-gray-400 flex-1 py-0.5 px-1">{{ $otForm->user->staff_no ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2 col-span-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Section/Line :</span>
                                @if($otForm->isEditable())
                                    <input type="text" name="section_line" value="{{ $otForm->section_line }}" class="border border-gray-300 rounded px-2 py-1 flex-1 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @else
                                    <span class="border-b border-gray-400 flex-1 py-0.5 px-1">{{ $otForm->section_line ?? '-' }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Main Table --}}
                        @include('ot-forms.partials._executive_plan')

                        {{-- Total Hours + Notes --}}
                        <div class="mt-4 text-sm">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="border-2 border-gray-800 rounded p-3">
                                    <div class="font-bold text-gray-700 mb-1">TOTAL HOURS (PLAN)</div>
                                    <div class="text-lg font-semibold"><span id="planTotalDisplay">0.00</span></div>
                                </div>
                                <div class="border-2 border-gray-800 rounded p-3">
                                    <div class="font-bold text-gray-700 mb-1">TOTAL HOURS (ACTUAL)</div>
                                    <div class="text-lg font-semibold"><span id="actualTotalDisplay">0.00</span></div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-600 space-y-1">
                                <p><b>Note:</b> Overtime submission should be presented to <b>HOD/DGM/MD</b> before 4.30 pm for approval.</p>
                                <p>OT claim shall be submitted to Payroll Section every <b>05th of the month</b> and the maximum claim shall not exceed <b>RM 500.00</b> per month.</p>
                            </div>
                        </div>

                        {{-- Approval Stamps --}}
                        <div class="mt-4">
                            <x-approval-stamps :stamps="$approvalStamps" />
                        </div>
                    @else
                        {{-- Non-Executive Form - existing layout --}}
                        {{-- Company Logos --}}
                        <div class="flex items-center justify-between mb-2 text-xs">
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-ingress-corp.svg') }}" alt="INGRESS CORPORATION" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>INGRESS CORP</span>'">
                                    <input type="checkbox" disabled class="rounded border-gray-300 h-3 w-3">
                                </label>
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-ingress-kata.svg') }}" alt="INGRESS KATAYAMA" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>INGRESS KATAYAMA</span>'">
                                    <input type="checkbox" disabled class="rounded border-gray-300 h-3 w-3">
                                </label>
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-ingress-eng.svg') }}" alt="INGRESS ENGINEERING" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>INGRESS ENG</span>'">
                                    <input type="checkbox" disabled class="rounded border-gray-300 h-3 w-3">
                                </label>
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-ingress-prec.svg') }}" alt="INGRESS PRECISION" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>INGRESS PREC</span>'">
                                    <input type="checkbox" disabled class="rounded border-gray-300 h-3 w-3">
                                </label>
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-ingress-center.svg') }}" alt="INGRESS CENTER" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>INGRESS CENTER</span>'">
                                    <input type="checkbox" disabled class="rounded border-gray-300 h-3 w-3">
                                </label>
                                <label class="flex items-center gap-1 border px-2 py-1">
                                    <img src="{{ asset('images/logo-talent-synergy.svg') }}" alt="TALENT SYNERGY" class="h-4 w-auto" onerror="this.outerHTML='<span class=\'font-bold text-[9px]\'>TALENT SYNERGY</span>'">
                                    <input type="checkbox" checked disabled class="rounded border-gray-300 h-3 w-3 text-indigo-600">
                                </label>
                            </div>
                        </div>

                        {{-- Form Title --}}
                        <div class="text-center mb-2">
                            <h3 class="text-sm font-bold">KUMPULAN SYARIKAT INGRESS</h3>
                            <p class="text-xs font-semibold">BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)</p>
                        </div>

                        {{-- Form Header Info --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mb-3 border p-2">
                            <div class="flex">
                                <span class="font-semibold w-24">NAMA:</span>
                                <span class="border-b border-gray-400 flex-1 px-1">{{ $otForm->user->name }}</span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold w-24">JABATAN:</span>
                                <span class="border-b border-gray-400 flex-1 px-1">{{ $otForm->user->department->name ?? '-' }}</span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold w-24">NO. KT:</span>
                                <span class="border-b border-gray-400 flex-1 px-1">{{ $otForm->user->staff_no ?? '-' }}</span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold w-24">BULAN:</span>
                                <span class="border-b border-gray-400 flex-1 px-1">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold w-24">JAWATAN:</span>
                                <span class="border-b border-gray-400 flex-1 px-1">{{ $otForm->user->designation ?? '-' }}</span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold w-24">SEKSYEN/BAH.:</span>
                                @if($otForm->isEditable())
                                    <input type="text" name="section_line" value="{{ $otForm->section_line }}"
                                           class="border-b border-gray-400 flex-1 px-1 text-xs py-0 h-5 focus:outline-none">
                                @else
                                    <span class="border-b border-gray-400 flex-1 px-1">{{ $otForm->section_line ?? '-' }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Main Table --}}
                        @include('ot-forms.partials._non_executive_plan')

                        {{-- Totals Row --}}
                        <div class="flex items-center gap-8 text-xs font-bold mt-2 border p-2">
                            <span>JUMLAH:</span>
                            <span>Plan: <span id="planTotalDisplay">0.00</span></span>
                            <span>Actual: <span id="actualTotalDisplay">0.00</span></span>
                        </div>

                        {{-- Approval Stamps Section: 3 boxes --}}
                        <div class="mt-4 grid grid-cols-3 gap-4">
                            {{-- DISEDIAKAN OLEH --}}
                            <div class="border rounded-lg p-3 text-center">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Disediakan Oleh :</p>
                                <div class="min-h-[60px] flex flex-col items-center justify-center">
                                    @if(!in_array($otForm->status, ['draft']))
                                        <p class="text-sm font-bold text-red-600">{{ strtoupper($otForm->user->name ?? '') }}</p>
                                        @if($otForm->plan_submitted_at)
                                            <p class="text-xs text-gray-500">{{ $otForm->plan_submitted_at->format('d/m/Y') }}</p>
                                        @endif
                                    @else
                                        <p class="text-xs text-gray-400 italic">—</p>
                                    @endif
                                </div>
                                <p class="text-xs font-semibold text-gray-600 border-t pt-1 mt-1">STAFF</p>
                            </div>
                            {{-- DISOKONG OLEH --}}
                            <div class="border rounded-lg p-3 text-center">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Disokong Oleh :</p>
                                <div class="min-h-[60px] flex flex-col items-center justify-center">
                                    @if($managerApproverName)
                                        <p class="text-sm font-bold text-red-600">{{ strtoupper($managerApproverName) }}</p>
                                    @elseif(in_array($otForm->status, ['pending_manager']))
                                        <p class="text-xs text-amber-500 font-semibold">PENDING</p>
                                    @else
                                        <p class="text-xs text-gray-400 italic">—</p>
                                    @endif
                                </div>
                                <p class="text-xs font-semibold text-gray-600 border-t pt-1 mt-1">MGR / HOD</p>
                            </div>
                            {{-- DILULUSKAN OLEH --}}
                            <div class="border rounded-lg p-3 text-center">
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Diluluskan Oleh :</p>
                                <div class="min-h-[60px] flex flex-col items-center justify-center">
                                    @if($gmApproverName)
                                        <p class="text-sm font-bold text-red-600">{{ strtoupper($gmApproverName) }}</p>
                                    @elseif(in_array($otForm->status, ['pending_gm']))
                                        <p class="text-xs text-amber-500 font-semibold">PENDING</p>
                                    @else
                                        <p class="text-xs text-gray-400 italic">—</p>
                                    @endif
                                </div>
                                <p class="text-xs font-semibold text-gray-600 border-t pt-1 mt-1">DGM / CEO</p>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div class="mt-2 text-[10px] text-gray-600">
                            <p>1) Borang OT mesti sampai ke Jabatan Sumber Manusia (Unit Payroll) selewat-lewatnya pada atau sebelum <b>5hb. setiap bulan</b> (bulan berikutnya).</p>
                        </div>
                    @endif
                </div>
            </form>

            {{-- Action Buttons --}}
            <div class="mt-4 flex items-center gap-4 no-print">
                @if($otForm->isEditable())
                    <button type="button" onclick="autoFillFromAttendance()" id="autoFillBtn"
                            class="inline-flex items-center px-6 py-2 bg-amber-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-amber-700">
                        Auto-Fill from Attendance
                    </button>
                    <button type="submit" form="otForm"
                            class="inline-flex items-center px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-indigo-700">
                        Save Draft
                    </button>
                    <button type="button" onclick="submitForApproval()"
                            class="inline-flex items-center px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-green-700">
                        Submit for Approval
                    </button>
                @endif

                {{-- Print button - always visible --}}
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center px-6 py-2 bg-gray-700 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-800">
                    Print Form
                </button>

                {{-- Export Excel - always visible --}}
                <a href="{{ route('ot-forms.preview-excel', $otForm) }}"
                   class="inline-flex items-center px-6 py-2 bg-green-700 text-white text-sm font-medium rounded-md shadow-sm hover:bg-green-800">
                    Export Excel
                </a>

                <a href="{{ route('ot-forms.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                    Back
                </a>
            </div>
    </div>

    @push('scripts')
    <script>
        // Auto-fill OT form actual times from attendance records
        async function autoFillFromAttendance() {
            if (!confirm('This will auto-fill actual OT times from your uploaded attendance PDF. Any existing actual times will be overwritten. Continue?')) return;

            const btn = document.getElementById('autoFillBtn');
            btn.disabled = true;
            btn.textContent = 'Loading...';

            try {
                const res = await fetch('{{ route("ot-forms.auto-fill", $otForm) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.error || data.message || 'Failed to auto-fill.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Auto-Fill from Attendance';
            }
        }

        // Auto-fill hidden project_name when dropdown changes
        function updateProjectName(entryId) {
            const select = document.getElementById('project-code-' + entryId);
            const nameInput = document.getElementById('project-name-' + entryId);
            if (!select || !nameInput) return;
            const opt = select.options[select.selectedIndex];
            nameInput.value = opt ? (opt.getAttribute('data-name') || '') : '';
        }

        // Calculate total hours for a row (planned or actual)
        function calcTotal(entryId, type) {
            const prefix = 'entries[' + entryId + ']';
            const startEl = document.querySelector('[name="' + prefix + '[' + type + '_start_time]"]');
            const endEl   = document.querySelector('[name="' + prefix + '[' + type + '_end_time]"]');
            const totalEl = document.getElementById(type + '-total-' + entryId);
            if (!startEl || !endEl || !totalEl) return;
            if (!startEl.value || !endEl.value) { totalEl.value = '0.00'; updateTotals(); return; }

            const [sh, sm] = startEl.value.split(':').map(Number);
            const [eh, em] = endEl.value.split(':').map(Number);
            let diff = (eh * 60 + em) - (sh * 60 + sm);
            if (diff < 0) diff += 1440; // Handle overnight
            const hours = Math.abs(diff / 60).toFixed(2);
            totalEl.value = hours;
            updateTotals();
        }

        // Update summary totals
        function updateTotals() {
            let planTotal = 0, actualTotal = 0;
            document.querySelectorAll('.plan-total').forEach(el => { planTotal += parseFloat(el.value) || 0; });
            document.querySelectorAll('.actual-total').forEach(el => { actualTotal += parseFloat(el.value) || 0; });
            document.getElementById('planTotalDisplay').textContent = planTotal.toFixed(2);
            document.getElementById('actualTotalDisplay').textContent = actualTotal.toFixed(2);
        }

        // Calculate OT1-OT5 based on day type and actual hours (Malaysian labor law)
        function calcOT(entryId) {
            const row = document.querySelector(`tr[data-entry-id="${entryId}"]`);
            if (!row) return;

            const actualTotalEl = document.getElementById('actual-total-' + entryId);
            const hours = parseFloat(actualTotalEl.value) || 0;
            const isWeekend = row.getAttribute('data-is-weekend') === '1';
            const phEl = document.getElementById('ph-' + entryId);
            const isPH = phEl && phEl.checked;

            const ot1El = document.getElementById('ot1-' + entryId);
            const ot2El = document.getElementById('ot2-' + entryId);
            const ot3El = document.getElementById('ot3-' + entryId);
            const ot4El = document.getElementById('ot4-' + entryId);
            const ot5El = document.getElementById('ot5-' + entryId);

            // Reset all
            ot1El.value = '0.00';
            ot2El.value = '0.00';
            ot3El.value = '0.00';
            ot4El.value = '0.00';
            ot5El.value = '0';

            if (hours <= 0) return;

            if (isPH) {
                // Public holiday: OT4 = excess hours (after 7.5h normal period)
                const ot4h = Math.max(hours - 7.5, 0);
                if (ot4h > 0) ot4El.value = ot4h.toFixed(2);
            } else if (isWeekend) {
                // Rest day (Sat/Sun): OT2 = first 7.5h, OT3 = excess, OT5 = 1 (count)
                const ot2h = Math.min(hours, 7.5);
                ot2El.value = ot2h.toFixed(2);
                const ot3h = Math.max(hours - 7.5, 0);
                if (ot3h > 0) ot3El.value = ot3h.toFixed(2);
                ot5El.value = '1';
            } else {
                // Normal day: OT1 = actual hours (after work)
                ot1El.value = hours.toFixed(2);
            }
        }

        // Run on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateTotals();
            // Calculate OT for all rows on load
            document.querySelectorAll('.entry-row[data-entry-id]').forEach(row => {
                const entryId = row.getAttribute('data-entry-id');
                calcOT(entryId);
            });
        });

        // Submit for HOD approval
        async function submitForApproval() {
            if (!confirm('Submit this OT form for HOD/MGR approval?')) return;

            const form = document.getElementById('otForm');
            const formData = new FormData(form);

            try {
                await fetch(form.action, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

                const res = await fetch('{{ route("ot-forms.submit-plan", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                if (data.success) { alert('Submitted for HOD/MGR approval!'); location.reload(); }
                else { alert(data.error || 'Failed to submit.'); }
            } catch (err) { alert('Error: ' + err.message); }
        }

        // Approve
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
                if (data.success) { alert('OT form approved!'); location.reload(); }
                else { alert(data.error || 'Failed to approve.'); }
            } catch (err) { alert('Error: ' + err.message); }
        }

        // Reject
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
                if (data.success) { alert('OT form rejected.'); location.reload(); }
                else { alert(data.error || 'Failed to reject.'); }
            } catch (err) { alert('Error: ' + err.message); }
        }
    </script>
    @endpush

    <x-help-button title="OT Form Edit Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Editing OT Form</h3>
            <p class="mb-3">Fill in your planned and actual overtime hours for each day of the month.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Steps</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Plan</strong> — Enter planned start/end times for anticipated OT</li>
                <li><strong>Auto-Fill Actual</strong> — Click the "Auto-Fill from Attendance" button to populate actual times from your uploaded attendance PDF</li>
                <li><strong>Save</strong> — Click "Save" to save your progress</li>
                <li><strong>Submit</strong> — Click "Submit for Approval" when ready</li>
                <li><strong>Print</strong> — Use the "Print" button to print the form</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">Important</h4>
            <p>Make sure you have uploaded the attendance PDF in your timesheet before using Auto-Fill. Project codes will also be auto-filled from your timesheet data.</p>
        </x-slot>
    </x-help-button>
</x-app-layout>
