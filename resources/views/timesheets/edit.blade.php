<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Timesheet — {{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}
            </h2>
            <div class="flex items-center gap-3">
                <span id="saveStatus" class="text-sm text-gray-400"></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($timesheet->status === 'draft') bg-gray-100 text-gray-800
                    @elseif($timesheet->status === 'pending_hod') bg-yellow-100 text-yellow-800
                    @elseif($timesheet->status === 'pending_l1') bg-blue-100 text-blue-800
                    @elseif($timesheet->status === 'approved') bg-green-100 text-green-800
                    @else bg-red-100 text-red-800 @endif">
                    @if($timesheet->status === 'pending_hod') Pending HOD Approval
                    @elseif($timesheet->status === 'pending_l1') Pending Assistant Manager Approval
                    @elseif(str_starts_with($timesheet->status, 'rejected')) Rejected
                    @else {{ str_replace('_', ' ', ucfirst($timesheet->status)) }} @endif
                </span>
            </div>
        </div>
    </x-slot>

    <div class="max-w-full mx-auto"
             x-data="timesheetMatrix()"
             x-init="init()">

            {{-- Header Info --}}
            @include('timesheets.partials._header')

            {{-- Attendance Upload (PDF / Excel) --}}
            @include('timesheets.partials._upload')

            {{-- Matrix Container --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto" id="matrixScroll">
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
                                        {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                        {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
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
                                            @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                                <input type="number"
                                                       step="0.5" min="0" max="24"
                                                       class="w-full h-full text-center text-xs border-0 p-0.5 focus:ring-1 focus:ring-indigo-400 bg-transparent"
                                                       :value="adminHours['{{ $type }}'][{{ $d }}]"
                                                       @input="updateAdmin('{{ $type }}', {{ $d }}, $event.target.value)"
                                                       @change="debounceSave()">
                                            @else
                                                <span class="text-xs" x-text="adminHours['{{ $type }}'][{{ $d }}] || ''"></span>
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50"
                                        x-text="adminRowTotal('{{ $type }}')"></td>
                                </tr>
                            @endforeach

                            {{-- TOTAL ADMIN JOB row --}}
                            <tr class="bg-gray-200 font-bold">
                                <td class="sticky left-0 z-10 bg-gray-200 border border-gray-300 px-2 py-1 text-[11px]">TOTAL ADMIN JOB</td>
                                <td class="sticky left-[180px] z-10 bg-gray-200 border border-gray-300 px-1 py-1"></td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    <td class="border border-gray-300 px-1 py-1 text-center text-xs"
                                        x-text="totalAdminForDay({{ $d }})"></td>
                                @endfor
                                <td class="border border-gray-300 px-1 py-1 text-center bg-gray-300"
                                    x-text="grandTotalAdmin()"></td>
                            </tr>

                            {{-- Spacer --}}
                            <tr><td :colspan="daysInMonth + 3" class="h-2 bg-gray-100 border-0"></td></tr>

                            {{-- ===== LOWER TABLE: Project Rows (flat iteration) ===== --}}
                            <template x-for="(fRow, fIdx) in flatProjectRows" :key="fRow.key">
                                <tr :class="fRow.sIdx === 0 ? 'border-t-2 border-gray-400' : ''">
                                    {{-- Project label cell: content only on first sub-row --}}
                                    <td class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-1 align-top min-w-[180px]"
                                        :class="fRow.sIdx === 0 ? '' : 'border-t-0'">
                                        <template x-if="fRow.sIdx === 0">
                                            <div>
                                                <div class="flex items-center gap-1 mb-1">
                                                    <span class="font-bold text-[11px]" x-text="'#' + (fRow.pIdx + 1)"></span>
                                                    @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                                        <button @click="removeProject(fRow.pIdx)" class="text-red-400 hover:text-red-600 text-[10px] ml-auto" title="Remove">&times;</button>
                                                    @endif
                                                </div>
                                                @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                                    <div x-data="tsProjectSelector(fRow.pIdx)" @click.away="closePortal()">
                                                        <div class="flex items-center gap-0.5">
                                                            <input type="text" x-ref="tsInput" x-model="search"
                                                                   @focus="openPortal()"
                                                                   @input.debounce.200ms="fetchResults()"
                                                                   @keydown.escape="closePortal()"
                                                                   :placeholder="displayText || '-- Search --'"
                                                                   class="w-full text-[10px] border-gray-300 rounded px-1 py-0.5" autocomplete="off">
                                                            <button x-show="displayText" @click.prevent="clearSel()" type="button" class="text-gray-400 hover:text-red-500 text-[10px]">&times;</button>
                                                        </div>
                                                        {{-- Manual entry field --}}
                                                        <template x-if="isSpecial">
                                                            <input type="text" x-model="manualName"
                                                                   @input="updateManual()"
                                                                   placeholder="Enter project code/name"
                                                                   class="w-full text-[10px] border-gray-300 rounded px-1 py-0.5 mt-0.5">
                                                        </template>
                                                    </div>
                                                    <div class="text-[9px] text-gray-400 mt-0.5 truncate" x-text="projectRows[fRow.pIdx].project_name"></div>
                                                @else
                                                    <div class="text-[10px] font-medium" x-text="projectRows[fRow.pIdx].project_name"></div>
                                                @endif
                                            </div>
                                        </template>
                                    </td>
                                    {{-- Sub-row type label --}}
                                    <td class="sticky left-[180px] z-10 bg-white border border-gray-300 px-1 py-0.5 text-center text-[9px] font-medium whitespace-nowrap"
                                        x-text="fRow.label"></td>
                                    {{-- Day cells --}}
                                    @for($d = 1; $d <= $daysInMonth; $d++)
                                        @php $day = $days[$d]; @endphp
                                        <td class="border border-gray-300 p-0 text-center
                                            {{ $day['day_type'] === 'off_day' ? 'bg-yellow-50' : '' }}
                                            {{ $day['day_type'] === 'public_holiday' ? 'bg-red-50' : '' }}
                                            {{ in_array($day['day_type'], ['mc', 'leave']) ? 'bg-orange-50' : '' }}">
                                            @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                                <input type="number"
                                                       step="0.5" min="0" max="24"
                                                       class="w-full h-full text-center text-xs border-0 p-0.5 focus:ring-1 focus:ring-indigo-400 bg-transparent"
                                                       :value="projectRows[fRow.pIdx].hours[{{ $d }}]?.[fRow.field] || 0"
                                                       @input="updateProjectHour(fRow.pIdx, {{ $d }}, fRow.field, $event.target.value)"
                                                       @change="debounceSave()">
                                            @else
                                                <span class="text-xs" x-text="projectRows[fRow.pIdx].hours[{{ $d }}]?.[fRow.field] || ''"></span>
                                            @endif
                                        </td>
                                    @endfor
                                    {{-- Row total --}}
                                    <td class="border border-gray-300 px-1 py-1 text-center font-semibold bg-gray-50 text-xs"
                                        x-text="projectSubRowTotal(fRow.pIdx, fRow.field)"></td>
                                </tr>
                            </template>

                            {{-- Add Project Button Row --}}
                            @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                <tr>
                                    <td colspan="2" class="sticky left-0 z-10 bg-white border border-gray-300 px-2 py-2">
                                        <button @click="addProject()" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                            + Add Project
                                        </button>
                                    </td>
                                    <td :colspan="daysInMonth + 1" class="border border-gray-300"></td>
                                </tr>
                            @endif

                            {{-- Spacer --}}
                            <tr><td :colspan="daysInMonth + 3" class="h-2 bg-gray-100 border-0"></td></tr>

                            {{-- ===== SUMMARY ROWS ===== --}}
                            @include('timesheets.partials._summary_rows')
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Notes and Legend Section --}}
            <div class="mt-6 bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Notes --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">NOTE:</h4>
                        <ul class="text-xs text-gray-600 space-y-1">
                            <li>• NORMAL DAY (EXCLUDE OT) - 8 HOURS</li>
                            <li>• FRIDAY ONLY (EXCLUDE OT) - 7 HOURS</li>
                        </ul>
                    </div>

                    {{-- Legend --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">LEGEND:</h4>
                        <ul class="text-xs text-gray-600 space-y-1">
                            <li>• NC - NORMAL COST</li>
                            <li>• COBQ - COST OF BAD QUALITY</li>
                            <li>• MKT - MARKETING</li>
                            <li>• PUR - PURCHASING</li>
                            <li>• R&D - RESEARCH & DEV</li>
                            <li>• TDR - TENDER</li>
                            <li>• RFQ - REQUEST FOR QUOTATION</li>
                            <li>• A.S.S - AFTER SALE SERVICE</li>
                        </ul>
                    </div>

                    {{-- Color Legend --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">COLUMN LEGEND:</h4>
                        <ul class="text-xs text-gray-600 space-y-1">
                            <li class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-yellow-100 border border-yellow-300 rounded"></span>
                                YELLOW - WEEKEND/REST DAY
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-red-100 border border-red-300 rounded"></span>
                                RED - PUBLIC HOLIDAY
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-orange-100 border border-orange-300 rounded"></span>
                                ORANGE - LEAVE
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Approval Stamps Section --}}
            <div class="mt-6 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">Approval Trail</h3>
                <x-approval-stamps :stamps="$approvalStamps" />

                {{-- Rejection Remarks --}}
                @if($timesheet->rejection_remarks)
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded">
                        <div class="text-xs font-medium text-red-800">Rejection Remarks:</div>
                        <div class="text-sm text-red-700 mt-1">{{ $timesheet->rejection_remarks }}</div>
                    </div>
                @endif
            </div>

            {{-- Approval Workflow Buttons --}}
            <div class="mt-6 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">Approval Actions</h3>

                {{-- Submit Button (Staff) - Only staff can submit their own timesheet --}}
                @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                    <div class="flex items-center gap-3">
                        <button @click="submitWithSignature('submit')" class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #4f46e5 !important; color: white !important;">
                            Submit for Approval
                        </button>
                        <span class="text-xs text-gray-500">Sign to submit</span>
                    </div>
                @endif

                {{-- Status message for non-draft timesheets --}}
                @if(!in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                    <div class="text-sm text-gray-600">
                        @if($timesheet->status === 'pending_hod')
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pending HOD Approval</span>
                        @elseif($timesheet->status === 'pending_l1')
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pending Assistant Manager Approval</span>
                        @elseif($timesheet->status === 'approved')
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded">Approved</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Save Button --}}
            @if(in_array($timesheet->status, ['draft', 'rejected_hod', 'rejected_l1']))
                <div class="mt-4 flex justify-between items-center">
                    <a href="{{ route('timesheets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to list</a>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('timesheets.export-excel', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                            Export Excel
                        </a>
                        <a href="{{ route('timesheets.export-pdf', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            Download PDF
                        </a>
                        <button @click="manualSave()" class="px-6 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #4f46e5 !important; color: white !important;">
                            Save Timesheet
                        </button>
                    </div>
                </div>
            @else
                <div class="mt-4 flex justify-between items-center">
                    <a href="{{ route('timesheets.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to list</a>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('timesheets.export-excel', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                            Export Excel
                        </a>
                        <a href="{{ route('timesheets.export-pdf', $timesheet) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            Download PDF
                        </a>
                    </div>
                </div>
            @endif

            {{-- Signature Pad Modal --}}
            <div x-show="showSignatureModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
                    <h3 class="text-lg font-semibold mb-4" x-text="signatureTitle"></h3>
                    <div class="border border-gray-300 rounded p-4 bg-gray-50">
                        <input type="text" x-model="signatureData" class="w-full border-0 bg-transparent text-center text-lg font-medium" placeholder="Type your full name as signature">
                    </div>
                    <div class="flex justify-between mt-4">
                        <div></div>
                        <div class="flex gap-2">
                            <button @click="closeSignatureModal()" class="px-4 py-2 rounded text-sm hover:shadow-md transition-all" style="background-color: #e5e7eb !important; color: #1f2937 !important;">Cancel</button>
                            <button @click="confirmSignature()" class="px-4 py-2 rounded text-sm hover:shadow-md transition-all" style="background-color: #4f46e5 !important; color: white !important;">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rejection Modal --}}
            <div x-show="showRejectionModalFlag" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
                    <h3 class="text-lg font-semibold mb-4">Reject Timesheet</h3>
                    <textarea x-model="rejectionRemarks" class="w-full border border-gray-300 rounded p-2 text-sm" rows="3" placeholder="Enter rejection remarks..."></textarea>
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="closeRejectionModal()" class="px-4 py-2 rounded text-sm hover:shadow-md transition-all" style="background-color: #e5e7eb !important; color: #1f2937 !important;">Cancel</button>
                        <button @click="confirmRejection()" class="px-4 py-2 rounded text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">Reject</button>
                    </div>
                </div>
            </div>
    </div>

    @push('scripts')
    <script>
    function timesheetMatrix() {
        return {
            timesheetId: @json($timesheet->id),
            daysInMonth: @json($daysInMonth),
            saveUrl: @json(route('timesheets.save', $timesheet)),
            csrfToken: document.querySelector('meta[name="csrf-token"]').content,
            staffName: @json(auth()->user()->name),

            adminHours: @json($adminData),
            projectRows: @json($projectRowsData),
            projectCodesLookup: @json($projectCodes->keyBy('id')->map(fn($pc) => ['code' => $pc->code, 'name' => $pc->name])),
            availableHours: @json(collect($days)->mapWithKeys(fn($d, $k) => [$k => $d['available_hours']])->toArray()),
            dayTypes: @json(collect($days)->mapWithKeys(fn($d, $k) => [$k => $d['day_type']])->toArray()),
            dayOfWeeks: @json(collect($days)->mapWithKeys(fn($d, $k) => [$k => $d['day_of_week']])->toArray()),

            subRowTypes: [
                { label: 'NORM/NC', field: 'normal_nc' },
                { label: 'NORM/COBQ', field: 'normal_cobq' },
                { label: 'OT/NC', field: 'ot_nc' },
                { label: 'OT/COBQ', field: 'ot_cobq' },
            ],

            get flatProjectRows() {
                let rows = [];
                this.projectRows.forEach((p, pIdx) => {
                    this.subRowTypes.forEach((sub, sIdx) => {
                        rows.push({
                            key: (p.id || pIdx) + '_' + sIdx,
                            pIdx: pIdx,
                            sIdx: sIdx,
                            field: sub.field,
                            label: sub.label,
                        });
                    });
                });
                return rows;
            },

            saveTimer: null,
            saving: false,

            // Signature pad
            showSignatureModal: false,
            signatureCanvas: null,
            signaturePad: null,
            signatureAction: null,
            signatureTitle: '',
            signatureData: null,

            // Rejection modal
            showRejectionModalFlag: false,
            rejectionAction: null,
            rejectionRemarks: '',

            init() {
                // Ensure all project rows have hours for each day
                this.projectRows.forEach(p => {
                    for (let d = 1; d <= this.daysInMonth; d++) {
                        if (!p.hours[d]) {
                            p.hours[d] = { normal_nc: 0, normal_cobq: 0, ot_nc: 0, ot_cobq: 0 };
                        }
                    }
                });
            },

            updateAdmin(type, day, value) {
                this.adminHours[type][day] = parseFloat(value) || 0;
            },

            updateProjectHour(pIdx, day, field, value) {
                if (!this.projectRows[pIdx].hours[day]) {
                    this.projectRows[pIdx].hours[day] = { normal_nc: 0, normal_cobq: 0, ot_nc: 0, ot_cobq: 0 };
                }
                this.projectRows[pIdx].hours[day][field] = parseFloat(value) || 0;
            },

            updateProjectCode(pIdx, codeId, category = null, manualName = null) {
                this.projectRows[pIdx].project_code_id = codeId ? parseInt(codeId) : null;
                this.projectRows[pIdx].project_category = category || null;
                this.projectRows[pIdx].manual_project_code_name = manualName || null;
                if (codeId && this.projectCodesLookup[codeId]) {
                    this.projectRows[pIdx].project_name = this.projectCodesLookup[codeId].name;
                } else if (category) {
                    this.projectRows[pIdx].project_name = category + (manualName ? ' - ' + manualName : '');
                } else {
                    this.projectRows[pIdx].project_name = '';
                }
                this.debounceSave();
            },

            addProject() {
                let newRow = {
                    id: 'new_' + Date.now(),
                    project_code_id: null,
                    project_category: null,
                    manual_project_code_name: null,
                    project_name: '',
                    row_order: this.projectRows.length + 1,
                    hours: {}
                };
                for (let d = 1; d <= this.daysInMonth; d++) {
                    newRow.hours[d] = { normal_nc: 0, normal_cobq: 0, ot_nc: 0, ot_cobq: 0 };
                }
                this.projectRows.push(newRow);
            },

            removeProject(pIdx) {
                if (confirm('Remove this project row?')) {
                    this.projectRows.splice(pIdx, 1);
                    this.debounceSave();
                }
            },

            // Totals
            adminRowTotal(type) {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) {
                    sum += parseFloat(this.adminHours[type]?.[d]) || 0;
                }
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },

            totalAdminForDay(day) {
                let sum = 0;
                for (let type in this.adminHours) {
                    sum += parseFloat(this.adminHours[type]?.[day]) || 0;
                }
                return parseFloat(sum.toFixed(1));
            },

            grandTotalAdmin() {
                let sum = 0;
                for (let type in this.adminHours) {
                    for (let d = 1; d <= this.daysInMonth; d++) {
                        sum += parseFloat(this.adminHours[type]?.[d]) || 0;
                    }
                }
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },

            projectSubRowTotal(pIdx, field) {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) {
                    sum += parseFloat(this.projectRows[pIdx]?.hours[d]?.[field]) || 0;
                }
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },

            // TOTAL EXTERNAL PROJECT = sum of all 4 sub-rows (normal_nc + normal_cobq + ot_nc + ot_cobq) per day
            totalExternalForDay(day) {
                let sum = 0;
                this.projectRows.forEach(p => {
                    let h = p.hours[day];
                    if (h) {
                        sum += (parseFloat(h.normal_nc) || 0)
                             + (parseFloat(h.normal_cobq) || 0)
                             + (parseFloat(h.ot_nc) || 0)
                             + (parseFloat(h.ot_cobq) || 0);
                    }
                });
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },

            // TOTAL WORKING HOURS = TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT
            totalWorkingForDay(day) {
                let admin = 0;
                for (let type in this.adminHours) {
                    admin += parseFloat(this.adminHours[type]?.[day]) || 0;
                }
                let external = parseFloat(this.totalExternalForDay(day)) || 0;
                let total = admin + external;
                return total ? parseFloat(total.toFixed(1)) : '';
            },

            getAvailableHoursForDay(day) {
                if (this.dayTypes[day] === 'absent') {
                    let working = parseFloat(this.totalWorkingForDay(day)) || 0;
                    if (working > 0) {
                        return this.dayOfWeeks[day] === 'FRI' ? 7 : 8;
                    } else {
                        return 0;
                    }
                }
                return parseFloat(this.availableHours[day]) || 0;
            },

            // OVERTIME = TOTAL WORKING HOURS - HOURS AVAILABLE
            overtimeForDay(day) {
                let working = parseFloat(this.totalWorkingForDay(day)) || 0;
                let available = parseFloat(this.getAvailableHoursForDay(day)) || 0;
                let ot = working - available;
                return ot > 0 ? parseFloat(ot.toFixed(1)) : '';
            },

            grandTotalExternal() {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) sum += parseFloat(this.totalExternalForDay(d)) || 0;
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },
            grandTotalWorking() {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) sum += parseFloat(this.totalWorkingForDay(d)) || 0;
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },
            grandTotalAvailable() {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) {
                    sum += parseFloat(this.getAvailableHoursForDay(d)) || 0;
                }
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },
            grandTotalOvertime() {
                let sum = 0;
                for (let d = 1; d <= this.daysInMonth; d++) sum += parseFloat(this.overtimeForDay(d)) || 0;
                return sum ? parseFloat(sum.toFixed(1)) : '';
            },

            // Save
            debounceSave() {
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.doSave(), 2000);
            },

            manualSave() {
                clearTimeout(this.saveTimer);
                this.doSave();
            },

            async doSave() {
                if (this.saving) return;
                this.saving = true;
                document.getElementById('saveStatus').textContent = 'Saving...';

                try {
                    const resp = await fetch(this.saveUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            admin_hours: this.adminHours,
                            project_rows: this.projectRows.map((p, idx) => ({
                                id: (typeof p.id === 'string' && p.id.startsWith('new_')) ? 'new' : p.id,
                                project_code_id: p.project_code_id,
                                project_category: p.project_category,
                                manual_project_code_name: p.manual_project_code_name,
                                project_name: p.project_name,
                                row_order: idx + 1,
                                hours: p.hours,
                            })),
                        }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        document.getElementById('saveStatus').textContent = 'Saved ✓';
                        setTimeout(() => { document.getElementById('saveStatus').textContent = ''; }, 3000);
                    } else {
                        document.getElementById('saveStatus').textContent = 'Save failed';
                    }
                } catch (e) {
                    document.getElementById('saveStatus').textContent = 'Save error';
                    console.error(e);
                } finally {
                    this.saving = false;
                }
            },

            // Signature pad functions
            showSignaturePad(action) {
                console.log('showSignaturePad called with action:', action);
                this.signatureAction = action;
                this.signatureTitle = action === 'submit' ? 'Sign to Submit' : 
                                      action === 'approve-l1' ? 'Sign to Approve (Asst Mgr)' :
                                      action === 'approve-l2' ? 'Sign to Approve (Mgr/HOD)' :
                                      'Sign to Approve (DGM/CEO)';
                this.signatureData = '';
                this.showSignatureModal = true;
            },

            closeSignatureModal() {
                this.showSignatureModal = false;
                this.signatureAction = null;
                this.signatureData = null;
            },

            clearSignature() {
                this.signatureData = '';
            },

            async submitWithSignature(action) {
                // Get user's full name
                const fullName = '{{ Auth::user()->name }}';
                // Extract suffix (everything after BIN/BINTI/B/BT)
                const suffixMatch = fullName.match(/\s+(BIN|BINTI|B|BT)\s+.+/i);
                const prefix = suffixMatch ? fullName.substring(0, suffixMatch.index).trim() : fullName;

                const signature = prompt(`Please enter your name to ${action === 'submit' ? 'submit' : 'approve'} this timesheet:\n\nYour full name: ${fullName}\n\nYou only need to type: ${prefix}\n\nType your name:`);
                if (!signature || !signature.trim()) {
                    alert('Please enter your signature');
                    return;
                }
                // Auto-complete if user only typed the prefix
                const finalSignature = signature.trim() === prefix ? fullName : signature.trim();
                // Validate signature matches staff name
                if (finalSignature.toLowerCase() !== this.staffName.toLowerCase()) {
                    alert('Signature must match your name: ' + this.staffName);
                    return;
                }
                // Execute the action
                await this.executeSignatureAction(action, finalSignature);
            },

            async confirmSignature() {
                console.log('confirmSignature called');
                console.log('signatureData:', this.signatureData);
                console.log('signatureAction:', this.signatureAction);
                if (!this.signatureData || !this.signatureData.trim()) {
                    alert('Please enter your signature');
                    return;
                }
                // Validate signature matches staff name
                if (this.signatureData.toLowerCase() !== this.staffName.toLowerCase()) {
                    alert('Signature must match your name: ' + this.staffName);
                    return;
                }
                // Store values in local variables
                const action = this.signatureAction;
                const signature = this.signatureData;
                console.log('Local variables - action:', action, 'signature:', signature);
                // Call the appropriate API and await it
                await this.executeSignatureAction(action, signature);
                // Then close modal after completion
                this.closeSignatureModal();
            },

            async executeSignatureAction(action, signature) {
                console.log('executeSignatureAction called, action:', action);
                console.log('signature:', signature);
                
                const actionUrls = {
                    'submit': @json(route('timesheets.submit', $timesheet)),
                    'approve-l1': @json(route('timesheets.approve-l1', $timesheet)),
                };

                const url = actionUrls[action];
                console.log('URL:', url);
                if (!url) {
                    console.error('No URL found for action:', action);
                    return;
                }

                try {
                    console.log('Sending request...');
                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            signature: signature,
                        }),
                    });
                    console.log('Response status:', resp.status);
                    console.log('Response headers:', resp.headers);
                    
                    const text = await resp.text();
                    console.log('Response text:', text);
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        alert('Server returned invalid response. Status: ' + resp.status + '\nResponse: ' + text.substring(0, 200));
                        return;
                    }
                    
                    console.log('Response data:', data);
                    if (data.success) {
                        console.log('Success, reloading...');
                        alert('Successfully submitted');
                        window.location.reload();
                    } else {
                        console.error('Action failed:', data.error);
                        alert('Action failed: ' + (data.error || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error:', e);
                    alert('Network error: ' + e.message);
                }
            },

            // Rejection functions
            showRejectionModal(action) {
                this.rejectionAction = action;
                this.rejectionRemarks = '';
                this.showRejectionModalFlag = true;
            },

            closeRejectionModal() {
                this.showRejectionModalFlag = false;
                this.rejectionAction = null;
                this.rejectionRemarks = '';
            },

            async confirmRejection() {
                if (!this.rejectionRemarks.trim()) {
                    alert('Please enter rejection remarks');
                    return;
                }

                const actionUrls = {
                    'reject-l1': @json(route('timesheets.reject-l1', $timesheet)),
                };

                const url = actionUrls[this.rejectionAction];
                if (!url) return;

                try {
                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            remarks: this.rejectionRemarks,
                        }),
                    });
                    const data = await resp.json();
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || 'Rejection failed');
                    }
                } catch (e) {
                    console.error(e);
                    alert('Error: ' + e.message);
                }
            },
        };
    }

    function tsProjectSelector(pIdx) {
        return {
            pIdx: pIdx,
            search: '',
            results: [],
            isSpecial: false,
            manualName: '',
            displayText: '',
            specialCategories: ['RFQ', 'MKT', 'PUR', 'R&D', 'A.S.S', 'TDR'],
            searchUrl: @json(route('api.project-codes.search')),
            _portalEl: null,
            _portalOpen: false,

            init() {
                const row = this.getParentData().projectRows[this.pIdx];
                if (row.project_category && this.specialCategories.includes(row.project_category)) {
                    this.displayText = row.project_category;
                    this.isSpecial = true;
                    this.manualName = row.manual_project_code_name || '';
                } else if (row.project_code_id) {
                    const lookup = this.getParentData().projectCodesLookup;
                    if (lookup[row.project_code_id]) {
                        this.displayText = lookup[row.project_code_id].code + ' - ' + (lookup[row.project_code_id].name || '');
                    }
                }
            },

            getParentData() {
                return Alpine.$data(this.$el.closest('[x-data*="timesheetMatrix"]'));
            },

            _esc(str) {
                const d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            },

            _buildPortalHtml() {
                let html = `<div style="max-height:220px;overflow-y:auto;background:#fff;border:1px solid #d1d5db;border-radius:0.375rem;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);font-size:10px;">`;
                html += `<div style="padding:3px 8px;font-size:9px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Manual Entry</div>`;
                this.specialCategories.forEach(cat => {
                    html += `<div data-action="category" data-cat="${cat}" style="padding:4px 8px;cursor:pointer;white-space:nowrap;" onmouseenter="this.style.background='#eef2ff'" onmouseleave="this.style.background='transparent'"><span style="font-weight:600;color:#4f46e5;">${cat}</span></div>`;
                });
                html += `<div style="padding:3px 8px;font-size:9px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-top:1px solid #e5e7eb;">Project Codes</div>`;
                if (this.results.length > 0) {
                    this.results.forEach(item => {
                        html += `<div data-action="project" data-id="${item.id}" data-code="${this._esc(item.code)}" data-name="${this._esc(item.name || '')}" style="padding:4px 8px;cursor:pointer;white-space:nowrap;" onmouseenter="this.style.background='#eef2ff'" onmouseleave="this.style.background='transparent'"><span style="font-weight:500;">${this._esc(item.code)}</span>${item.name ? ' <span style="color:#9ca3af;">- ' + this._esc(item.name) + '</span>' : ''}</div>`;
                    });
                } else if (this.search.length > 0) {
                    html += `<div style="padding:6px;text-align:center;color:#9ca3af;">No results</div>`;
                } else {
                    html += `<div style="padding:6px;text-align:center;color:#9ca3af;">Loading...</div>`;
                }
                html += `</div>`;
                return html;
            },

            _renderPortal() {
                if (!this._portalEl) {
                    const el = document.createElement('div');
                    el.style.cssText = 'position:fixed;z-index:99999;';
                    document.body.appendChild(el);
                    this._portalEl = el;

                    document.addEventListener('mousedown', (e) => {
                        if (this._portalEl && !this._portalEl.contains(e.target) && !this.$el.contains(e.target)) {
                            this.closePortal();
                        }
                    });
                    const reposition = () => { if (this._portalOpen) this._positionPortal(); };
                    window.addEventListener('scroll', reposition, true);
                    window.addEventListener('resize', reposition, true);
                }

                this._positionPortal();
                this._portalEl.innerHTML = this._buildPortalHtml();
                this._portalEl.style.display = 'block';

                this._portalEl.querySelectorAll('[data-action="category"]').forEach(el => {
                    el.addEventListener('mousedown', (e) => { e.preventDefault(); this.pickCategory(el.dataset.cat); });
                });
                this._portalEl.querySelectorAll('[data-action="project"]').forEach(el => {
                    el.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        this.pickProject({ id: parseInt(el.dataset.id), code: el.dataset.code, name: el.dataset.name });
                    });
                });
            },

            _positionPortal() {
                if (!this._portalEl || !this.$refs.tsInput) return;
                const rect = this.$refs.tsInput.getBoundingClientRect();
                const w = Math.max(rect.width, 200);
                this._portalEl.style.top = (rect.bottom + 2) + 'px';
                this._portalEl.style.left = rect.left + 'px';
                this._portalEl.style.width = w + 'px';
            },

            openPortal() {
                this._portalOpen = true;
                this._renderPortal();
                this.fetchResults();
            },

            closePortal() {
                this._portalOpen = false;
                if (this._portalEl) this._portalEl.style.display = 'none';
            },

            fetchResults() {
                const q = this.search || '';
                fetch(this.searchUrl + '?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        this.results = data;
                        if (this._portalOpen) this._renderPortal();
                    })
                    .catch(() => {});
            },

            pickProject(item) {
                this.displayText = item.code + (item.name ? ' - ' + item.name : '');
                this.isSpecial = false;
                this.manualName = '';
                this.search = '';
                this.closePortal();
                this.getParentData().updateProjectCode(this.pIdx, item.id, null, null);
            },

            pickCategory(cat) {
                this.displayText = cat;
                this.isSpecial = true;
                this.manualName = '';
                this.search = '';
                this.closePortal();
                this.getParentData().updateProjectCode(this.pIdx, null, cat, null);
            },

            updateManual() {
                this.getParentData().updateProjectCode(this.pIdx, null, this.getParentData().projectRows[this.pIdx].project_category, this.manualName);
            },

            clearSel() {
                this.displayText = '';
                this.isSpecial = false;
                this.manualName = '';
                this.search = '';
                this.closePortal();
                this.getParentData().updateProjectCode(this.pIdx, null, null, null);
            },
        };
    }
    </script>
    @endpush

    <x-help-button title="Edit Timesheet Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Editing Your Timesheet</h3>
            <p class="mb-3">Fill in your daily work hours for this month.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Steps</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Upload Attendance</strong> — Upload your Infotech attendance PDF to auto-fill check-in/out times</li>
                <li><strong>Admin Hours</strong> — Hours are auto-filled from attendance data</li>
                <li><strong>Project Rows</strong> — Add project codes and enter hours for each day (Normal/OT, NC/COBQ)</li>
                <li><strong>Auto-Save</strong> — Changes are saved automatically as you type</li>
                <li><strong>Submit</strong> — Use <strong>"Submit for Approval"</strong> button to submit for approval</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">Tips</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li>Scroll horizontally to see all days in the month</li>
                <li>Summary row shows totals for each day</li>
                <li>Ensure project hours match available hours for each day</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
