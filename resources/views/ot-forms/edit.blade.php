@php
session(['hr_last_seen' => now()]);
session(['ot_forms_last_seen' => now()]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="font-bold text-xl text-gray-800 leading-tight">
                    {{ $otForm->isExecutive() ? 'OVERTIME CLAIM FORM (EXECUTIVE)' : 'BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)' }}
                </h2>
            </div>
            @php
                $badgeClass = match($otForm->status) {
                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                    'pending_manager' => 'bg-amber-50 text-amber-700 border-amber-300',
                    'pending_hr' => 'bg-cyan-50 text-cyan-700 border-cyan-300',
                    'pending_gm' => 'bg-blue-50 text-blue-700 border-blue-300',
                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
                    'rejected' => 'bg-red-50 text-red-700 border-red-300',
                    'returned_hr' => 'bg-orange-50 text-orange-700 border-orange-300',
                    default => 'bg-gray-100 text-gray-700 border-gray-300',
                };
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $badgeClass }} shadow-sm">
                {{ $otForm->status_label }}
            </span>
        </div>
    </x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    <div class="max-w-7xl mx-auto pb-8">

            @if(session('success'))
                <div class="mb-4 bg-gradient-to-r from-emerald-50 to-green-50 border-l-4 border-emerald-500 text-emerald-800 px-4 py-3 rounded-r-lg shadow-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-r-lg shadow-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            {{-- Guard: No attendance records (PDF not uploaded) --}}
            @if(!$hasAttendance)
                <div id="noAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md mx-4 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-amber-100 rounded-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">PDF Attendance Required</h3>
                        <p class="text-gray-600 mb-6">Please upload your PDF Attendance from Infotech in the Timesheet first. The system needs this to determine Public Holiday dates.</p>
                        <a href="{{ route('timesheets.index') }}"
                           class="inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition shadow-md">
                            Okay
                        </a>
                    </div>
                </div>
            @endif

            {{-- HR Correction Notes --}}
            @if($otForm->hr_remarks)
                <div class="mb-4 bg-gradient-to-r from-yellow-50 to-amber-50 border-l-4 border-yellow-500 text-yellow-900 px-4 py-3 rounded-r-lg shadow-sm">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <div>
                            <p class="font-semibold">HR Correction Notes:</p>
                            <p class="mt-1 text-sm">Edited by {{ $otForm->hrEditor?->name ?? 'HR' }} on {{ $otForm->hr_edited_at?->format('d/m/Y H:i') }}</p>
                            <div class="mt-2 space-y-2 leading-relaxed">
                                @foreach(explode("\n\n", $otForm->hr_remarks) as $dateBlock)
                                    <div class="text-sm">{!! nl2br(e($dateBlock)) !!}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- HR Return Reason Notification --}}
            @if($otForm->status === 'returned_hr')
                @php
                    $hrReturnLog = $otForm->approvalLogs()->where('action', 'hr_returned')->orderBy('acted_at', 'desc')->first();
                @endphp
                @if($hrReturnLog && $hrReturnLog->remarks)
                    <div class="mb-4 bg-gradient-to-r from-orange-50 to-amber-50 border-l-4 border-orange-500 text-orange-800 px-4 py-3 rounded-r-lg shadow-sm">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-orange-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <p class="font-semibold">HR Return Reason:</p>
                                <p class="mt-1">{{ $hrReturnLog->remarks }}</p>
                                <p class="mt-2 text-sm text-orange-600">Please make the necessary corrections and resubmit.</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Auto-Fill button at top (only if editable) --}}
            @if($otForm->isEditable())
                <div class="mb-4">
                    <button type="button" onclick="autoFillFromAttendance()" id="autoFillBtn"
                            class="inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                            style="background-color: #f59e0b !important; color: white !important;">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11 v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Auto-Fill from Attendance
                    </button>
                </div>
            @endif

            <form method="POST" action="{{ route('ot-forms.save', $otForm) }}" id="otForm">
                @csrf
                @method('PUT')

                @php
                    $hasHrCorrections = $otForm->entries->contains(fn ($e) => !empty($e->hr_corrections));
                @endphp

                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
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
                        <div class="grid grid-cols-3 gap-x-6 gap-y-3 mb-5 text-sm bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-5 shadow-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Name :</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-1 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Department :</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-1 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->department->name ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Month :</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-1 rounded flex-1 font-medium text-gray-800">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Staff No :</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-1 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->staff_no ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2 col-span-2">
                                <span class="font-semibold text-gray-700 w-28 shrink-0">Section/Line :</span>
                                @if($otForm->isEditable())
                                    <input type="text" name="section_line" value="{{ $otForm->section_line }}" class="border-2 border-indigo-200 rounded-lg px-3 py-1.5 flex-1 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white transition-all">
                                @else
                                    <span class="border-b-2 border-indigo-200 bg-white px-2 py-1 rounded flex-1 font-medium text-gray-800">{{ $otForm->section_line ?? '-' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($hasHrCorrections)
                            <div class="mb-3 flex items-center gap-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><span class="line-through">Crossed-out</span> times indicate the original values changed by HR.</span>
                            </div>
                        @endif

                        {{-- Main Table --}}
                        @include('ot-forms.partials._executive_plan')

                        {{-- Total Hours + Notes --}}
                        <div class="mt-4 text-sm">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <div class="font-bold text-indigo-800">TOTAL HOURS (PLAN)</div>
                                    </div>
                                    <div class="text-2xl font-bold text-indigo-900"><span id="planTotalDisplay">0.00</span></div>
                                </div>
                                <div class="bg-gradient-to-br from-emerald-50 to-green-50 border-2 border-emerald-200 rounded-xl p-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div class="font-bold text-emerald-800">TOTAL HOURS (ACTUAL)</div>
                                    </div>
                                    <div class="text-2xl font-bold text-emerald-900"><span id="actualTotalDisplay">0.00</span></div>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-lg p-3 text-xs text-gray-700 space-y-1.5">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p>Overtime submission should be presented to <b>HOD/DGM/MD</b> before 4.30 pm for approval.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p>OT claim shall be submitted to Payroll Section every <b>05th of the month</b> and the maximum claim shall not exceed <b>RM 500.00</b> per month.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Approval Stamps --}}
                        <div class="mt-4">
                            <x-approval-stamps :stamps="$approvalStamps" />
                        </div>
                    @else
                        {{-- Non-Executive Form - existing layout --}}
                        {{-- Form Title --}}
                        <div class="text-center mb-2">
                            <h3 class="text-sm font-bold">KUMPULAN SYARIKAT INGRESS</h3>
                            <p class="text-xs font-semibold">BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)</p>
                        </div>

                        {{-- Form Header Info --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mb-3 bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-xl p-3 shadow-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">NAMA:</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">JABATAN:</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->department->name ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">NO. KT:</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->staff_no ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">BULAN:</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">JAWATAN:</span>
                                <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ $otForm->user->designation ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold w-24 shrink-0 text-gray-700">SEKSYEN/BAH.:</span>
                                @if($otForm->isEditable())
                                    <input type="text" name="section_line" value="{{ $otForm->section_line }}" class="border-2 border-indigo-200 rounded-lg px-3 py-1 flex-1 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white transition-all">
                                @else
                                    <span class="border-b-2 border-indigo-200 bg-white px-2 py-0.5 rounded flex-1 font-medium text-gray-800">{{ $otForm->section_line ?? '-' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($hasHrCorrections)
                            <div class="mb-3 flex items-center gap-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><span class="line-through">Crossed-out</span> times indicate the original values changed by HR.</span>
                            </div>
                        @endif

                        {{-- Main Table --}}
                        @include('ot-forms.partials._non_executive_plan')

                        {{-- Totals Row --}}
                        <div class="flex items-center gap-6 text-xs font-bold mt-3 bg-gradient-to-r from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-3 shadow-sm">
                            <span class="text-indigo-800">JUMLAH:</span>
                            <span class="text-indigo-900">Plan: <span id="planTotalDisplay" class="font-bold">0.00</span></span>
                            <span class="text-emerald-900">Actual: <span id="actualTotalDisplay" class="font-bold">0.00</span></span>
                        </div>

                        {{-- Approval Stamps --}}
                        <div class="mt-4">
                            <x-approval-stamps :stamps="$approvalStamps" />
                        </div>

                        {{-- Notes --}}
                        <div class="mt-3 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-lg p-3 text-[10px] text-gray-700">
                            <div class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p>1) Borang OT mesti sampai ke Jabatan Sumber Manusia (Unit Payroll) selewat-lewatnya pada atau sebelum <b>5hb. setiap bulan</b> (bulan berikutnya).</p>
                            </div>
                        </div>
                    @endif
                </div>
            </form>

            {{-- Action Buttons --}}
            <div class="mt-6 flex items-center justify-between gap-4">
                {{-- Left: Back button with Submit stacked on top --}}
                <div class="flex flex-col gap-3">
                    @if($otForm->isEditable())
                        <button type="button" onclick="submitForApproval()"
                                class="inline-flex items-center px-6 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                                style="background-color: #10b981 !important; color: white !important;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Submit for Approval
                        </button>
                    @endif
                    @if($canUnsubmit)
                        <button type="button" onclick="unsubmitOtForm()"
                                class="inline-flex items-center px-6 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 bg-gray-200 text-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                            </svg>
                            Unsubmit
                        </button>
                    @endif
                    <a href="{{ route('ot-forms.index') }}"
                       class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </a>
                </div>

                {{-- Right: Export Excel, Export PDF, Save Draft --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('ot-forms.export-excel', $otForm) }}"
                       class="inline-flex items-center px-6 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                       style="background-color: #16a34a !important; color: white !important;">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export Excel
                    </a>
                    <a href="{{ route('ot-forms.export-pdf', $otForm) }}"
                       class="inline-flex items-center px-6 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                       style="background-color: #dc2626 !important; color: white !important;">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Download PDF
                    </a>
                    @if($otForm->isEditable())
                        <button type="submit" form="otForm"
                                class="inline-flex items-center px-6 py-2.5 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                                style="background-color: #4f46e5 !important; color: white !important;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Draft
                        </button>
                    @endif
                </div>
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

        // Clear a single actual time field (start or end) and recalculate totals
        function clearActualTime(entryId, field) {
            const input = document.querySelector('[name="entries[' + entryId + '][actual_' + field + '_time]"]');
            if (input) {
                input.value = '';
                calcTotal(entryId, 'actual');
                if (typeof calcOT === 'function') calcOT(entryId);
            }
        }

        // Floor hours down to nearest 0.25 increment
        function floorQuarter(hours) {
            return Math.floor(hours * 4) / 4;
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
            let hours = Math.abs(diff / 60);
            // Floor actual hours to 0.25 increment for OT distribution
            if (type === 'actual') hours = floorQuarter(hours);
            totalEl.value = hours.toFixed(2);
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

        // Add a new entry row for the given date
        async function addEntryRow(dateStr) {
            try {
                const res = await fetch('{{ route("ot-forms.add-entry", $otForm) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ entry_date: dateStr }),
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to add entry.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        // Delete an extra entry row
        async function deleteEntryRow(entryId) {
            if (!confirm('Remove this entry row?')) return;
            try {
                const res = await fetch('{{ url("ot-forms") }}/{{ $otForm->id }}/entries/' + entryId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Failed to delete entry.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        // Set OT value on either an input or a span/td, and update hidden counterpart if present
        function setOtValue(el, value) {
            if (!el) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.value = value;
            } else {
                el.textContent = value;
            }
            const hidden = document.getElementById(el.id + '-input');
            if (hidden) hidden.value = value;
        }

        // Calculate OT1-OT5 based on day type and actual hours (Malaysian labor law)
        function calcOT(entryId) {
            const row = document.querySelector(`tr[data-entry-id="${entryId}"]`);
            if (!row) return;

            const actualTotalEl = document.getElementById('actual-total-' + entryId);
            let hours = parseFloat(actualTotalEl?.value) || 0;
            hours = floorQuarter(hours);
            const isWeekend = row.getAttribute('data-is-weekend') === '1';
            const phEl = document.getElementById('ph-' + entryId);
            const isPH = phEl && phEl.checked;

            const ot1El = document.getElementById('ot1-' + entryId);
            const ot2El = document.getElementById('ot2-' + entryId);
            const ot3El = document.getElementById('ot3-' + entryId);
            const ot4El = document.getElementById('ot4-' + entryId);
            const ot5El = document.getElementById('ot5-' + entryId);

            // Detect executive form by absence of ot4 field
            const isExecutive = !ot4El;

            // Reset all
            setOtValue(ot1El, '0.00');
            setOtValue(ot2El, '0.00');
            setOtValue(ot3El, '0.00');
            setOtValue(ot4El, '0.00');
            setOtValue(ot5El, '0');

            if (hours <= 0) return;

            if (isExecutive) {
                // Executive: all hours go to the matching day type bucket
                if (isPH) {
                    setOtValue(ot3El, hours.toFixed(2));
                } else if (isWeekend) {
                    setOtValue(ot2El, hours.toFixed(2));
                } else {
                    setOtValue(ot1El, hours.toFixed(2));
                }
            } else if (isPH) {
                // Non-exec public holiday: OT2 = first 7.5h, OT4 = excess
                const ot2h = Math.min(hours, 7.5);
                setOtValue(ot2El, ot2h.toFixed(2));
                const ot4h = Math.max(hours - 7.5, 0);
                if (ot4h > 0) setOtValue(ot4El, ot4h.toFixed(2));
            } else if (isWeekend) {
                // Non-exec rest day: OT2 = first 7.5h, OT3 = excess, OT5 = 1
                const ot2h = Math.min(hours, 7.5);
                setOtValue(ot2El, ot2h.toFixed(2));
                const ot3h = Math.max(hours - 7.5, 0);
                if (ot3h > 0) setOtValue(ot3El, ot3h.toFixed(2));
                setOtValue(ot5El, '1');
            } else {
                // Normal day: OT1 = actual hours
                setOtValue(ot1El, hours.toFixed(2));
            }

            // Auto-tick meal checkbox on normal day when OT is 3 hours or more (non-exec only)
            const mealEl = document.getElementById('meal-' + entryId);
            if (mealEl && !isExecutive) {
                if (!isPH && !isWeekend && hours >= 3) {
                    mealEl.checked = true;
                } else if (!isPH && !isWeekend) {
                    mealEl.checked = false;
                }
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

        // Unsubmit OT form (return to draft for corrections)
        async function unsubmitOtForm() {
            if (!confirm('Are you sure you want to unsubmit this OT form? This will return it to draft for corrections and approvers will not see it anymore.')) return;

            try {
                const res = await fetch('{{ route("ot-forms.unsubmit", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                if (data.success) { alert('OT form unsubmitted. You can now edit it.'); location.reload(); }
                else { alert(data.error || 'Failed to unsubmit.'); }
            } catch (err) { alert('Error: ' + err.message); }
        }

        // Approve
        async function approveForm() {
            if (!confirm('Are you sure you want to approve this OT form?')) return;

            const signature = '{{ Auth::user()->short_name ?? Auth::user()->name }}';
            try {
                const res = await fetch('{{ route("approvals.ot-forms.approve", $otForm) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ signature: signature }),
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

    <x-help-button title="Edit OT Form Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Editing OT Form</h3>
            <p class="mb-3">Fill in planned and actual overtime hours for each day of the month.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Steps</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Plan</strong> — Enter planned start/end times for expected OT</li>
                <li><strong>Add OT Entry</strong> — Click <strong>"+"</strong> button next to date to add additional OT entry on the same day (if you have more than one OT session on the same day)</li>
                <li><strong>Auto-Fill Actual</strong> — Click <strong>"Auto-Fill from Attendance"</strong> to fill actual times from your uploaded attendance PDF</li>
                <li><strong>Save</strong> — Click <strong>"Save Draft"</strong> to save your progress</li>
                <li><strong>Submit</strong> — Click <strong>"Submit for Approval"</strong> when ready</li>
                <li><strong>Export</strong> — Use <strong>"Export Excel"</strong> or <strong>"Download PDF"</strong> to export the form</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">Important</h4>
            <p>Make sure you have uploaded your attendance PDF in timesheet before using Auto-Fill. Project codes will also be auto-filled from your timesheet data.</p>
        </x-slot>
    </x-help-button>
</x-app-layout>
