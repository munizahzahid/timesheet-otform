@php
    $editMode = $editMode ?? false;
    $statusOptions = [
        '' => '— Select Status —',
        'active' => 'Active',
        'completed' => 'Completed',
        'delayed' => 'Delayed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];
@endphp

<div class="space-y-6">
    {{-- Project Summary Section --}}
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Project Summary</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left: Basic Info --}}
                <div class="lg:col-span-2 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        @include('admin.project.partials._field', [
                            'name' => 'project_name',
                            'label' => 'Project Name',
                            'value' => $project->project_name,
                            'editMode' => $editMode,
                        ])
                        @include('admin.project.partials._field', [
                            'name' => 'project_code',
                            'label' => 'Project Code',
                            'value' => $project->project_code,
                            'editMode' => $editMode,
                        ])
                    </div>

                    @include('admin.project.partials._field', [
                        'name' => 'description',
                        'label' => 'Description',
                        'type' => 'textarea',
                        'value' => $project->description,
                        'editMode' => $editMode,
                        'rows' => 2,
                    ])

                    <div class="grid grid-cols-2 gap-4">
                        @include('admin.project.partials._field', [
                            'name' => 'status',
                            'label' => 'Status',
                            'type' => 'select',
                            'value' => $project->status,
                            'options' => $statusOptions,
                            'editMode' => $editMode,
                        ])
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Created By</p>
                            <p class="text-sm text-gray-700">{{ $project->createdBy->name ?? 'System' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Right: Progress Stats --}}
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Overall Progress</h4>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Plan Progress</span>
                                <span class="font-medium text-gray-900">{{ $project->overall_plan_progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                <div class="h-3 rounded-full" style="width: {{ $project->overall_plan_progress }}%; background-color: #3b82f6;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600">Actual Progress</span>
                                <span class="font-medium text-gray-900">{{ $project->overall_actual_progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                <div class="h-3 rounded-full" style="width: {{ $project->overall_actual_progress }}%; background-color: #22c55e;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Date Information --}}
            <div class="mt-6 pt-6 border-t border-gray-100">
                <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Timeline</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Planned</p>
                        <div class="grid grid-cols-2 gap-3">
                            @include('admin.project.partials._field', [
                                'name' => 'start_date_plan',
                                'label' => 'Start',
                                'type' => 'date',
                                'value' => $project->start_date_plan,
                                'editMode' => $editMode,
                            ])
                            @include('admin.project.partials._field', [
                                'name' => 'end_date_plan',
                                'label' => 'End',
                                'type' => 'date',
                                'value' => $project->end_date_plan,
                                'editMode' => $editMode,
                            ])
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Actual</p>
                        <div class="grid grid-cols-2 gap-3">
                            @include('admin.project.partials._field', [
                                'name' => 'start_date_actual',
                                'label' => 'Start',
                                'type' => 'date',
                                'value' => $project->start_date_actual,
                                'editMode' => $editMode,
                            ])
                            @include('admin.project.partials._field', [
                                'name' => 'end_date_actual',
                                'label' => 'End',
                                'type' => 'date',
                                'value' => $project->end_date_actual,
                                'editMode' => $editMode,
                            ])
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Revised</p>
                        <div class="grid grid-cols-2 gap-3">
                            @include('admin.project.partials._field', [
                                'name' => 'start_date_revise',
                                'label' => 'Start',
                                'type' => 'date',
                                'value' => $project->start_date_revise,
                                'editMode' => $editMode,
                            ])
                            @include('admin.project.partials._field', [
                                'name' => 'end_date_revise',
                                'label' => 'End',
                                'type' => 'date',
                                'value' => $project->end_date_revise,
                                'editMode' => $editMode,
                            ])
                        </div>
                    </div>
                </div>
            </div>

            {{-- Phases Summary --}}
            @if($project->phases->count() > 0)
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Phases ({{ $project->phases->count() }})</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($project->phases as $phase)
                            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-medium text-gray-900">{{ $phase->phase_name }}</p>
                                    <span class="text-xs text-gray-500">#{{ $phase->phase_order }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                        <div class="h-2.5 rounded-full" style="width: {{ $phase->progress_actual }}%; background-color: #22c55e;"></div>
                                    </div>
                                    <span class="text-xs text-gray-600">{{ $phase->progress_actual }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Desknet Project Details --}}
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Desknet Project Details</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Project Manager --}}
                <div class="space-y-3">
                    @include('admin.project.partials._staff_picker', [
                        'name' => 'project_manager',
                        'staffIdName' => 'project_manager_staff_id',
                        'departmentName' => 'project_manager_department',
                        'label' => 'Project Manager',
                        'value' => $project->project_manager,
                        'staffIdValue' => $project->project_manager_staff_id,
                        'departmentValue' => $project->project_manager_department,
                        'staffList' => $staffList,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'project_manager_staff_id',
                        'label' => 'Staff ID',
                        'value' => $project->project_manager_staff_id,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'project_manager_department',
                        'label' => 'Department',
                        'value' => $project->project_manager_department,
                        'editMode' => $editMode,
                    ])
                </div>

                {{-- Deskman 1 --}}
                <div class="space-y-3">
                    @include('admin.project.partials._staff_picker', [
                        'name' => 'deskman_1',
                        'staffIdName' => 'deskman_1_staff_id',
                        'departmentName' => 'deskman_1_department',
                        'label' => 'Project Deskman 1',
                        'value' => $project->deskman_1,
                        'staffIdValue' => $project->deskman_1_staff_id,
                        'departmentValue' => $project->deskman_1_department,
                        'staffList' => $staffList,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'deskman_1_staff_id',
                        'label' => 'Staff ID',
                        'value' => $project->deskman_1_staff_id,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'deskman_1_department',
                        'label' => 'Department',
                        'value' => $project->deskman_1_department,
                        'editMode' => $editMode,
                    ])
                </div>

                {{-- Deskman 2 --}}
                <div class="space-y-3">
                    @include('admin.project.partials._staff_picker', [
                        'name' => 'deskman_2',
                        'staffIdName' => 'deskman_2_staff_id',
                        'departmentName' => 'deskman_2_department',
                        'label' => 'Project Deskman 2',
                        'value' => $project->deskman_2,
                        'staffIdValue' => $project->deskman_2_staff_id,
                        'departmentValue' => $project->deskman_2_department,
                        'staffList' => $staffList,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'deskman_2_staff_id',
                        'label' => 'Staff ID',
                        'value' => $project->deskman_2_staff_id,
                        'editMode' => $editMode,
                    ])
                    @include('admin.project.partials._field', [
                        'name' => 'deskman_2_department',
                        'label' => 'Department',
                        'value' => $project->deskman_2_department,
                        'editMode' => $editMode,
                    ])
                </div>

                @include('admin.project.partials._field', [
                    'name' => 'client',
                    'label' => 'Client',
                    'value' => $project->client,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'attn',
                    'label' => 'Attn',
                    'value' => $project->attn,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'po_no',
                    'label' => 'PO No.',
                    'value' => $project->po_no,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'year',
                    'label' => 'Year',
                    'type' => 'number',
                    'value' => $project->year,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'project_value',
                    'label' => 'Project Value',
                    'type' => 'number',
                    'value' => $project->project_value,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'purchasing_budget_100',
                    'label' => 'Purchasing Budget 100%',
                    'type' => 'number',
                    'value' => $project->purchasing_budget_100,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'purchasing_budget_95',
                    'label' => 'Purchasing Budget 95%',
                    'type' => 'number',
                    'value' => $project->purchasing_budget_95,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'tin',
                    'label' => 'TIN',
                    'value' => $project->tin,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'identification_no',
                    'label' => 'Identification No',
                    'value' => $project->identification_no,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'exemption_cert_no',
                    'label' => 'Exemption Cert. No',
                    'value' => $project->exemption_cert_no,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'contact_no',
                    'label' => 'Contact No',
                    'value' => $project->contact_no,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'value' => $project->email,
                    'editMode' => $editMode,
                ])
                @include('admin.project.partials._field', [
                    'name' => 'project_schedule_status',
                    'label' => 'Schedule Status',
                    'value' => $project->project_schedule_status,
                    'editMode' => $editMode,
                ])
            </div>

            @include('admin.project.partials._field', [
                'name' => 'full_address',
                'label' => 'Full Address',
                'type' => 'textarea',
                'value' => $project->full_address,
                'editMode' => $editMode,
                'rows' => 2,
            ])

            <div class="mt-6">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Payment Terms</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    @foreach(['term_1', 'term_2', 'term_3', 'term_4', 'term_5'] as $termName)
                        @include('admin.project.partials._field', [
                            'name' => $termName,
                            'label' => 'Term ' . substr($termName, -1),
                            'value' => $project->{$termName},
                            'editMode' => $editMode,
                        ])
                    @endforeach
                </div>
            </div>

            {{-- Attachments --}}
            @if(!empty($project->attachment_po_customer) || !empty($project->other_attachments))
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Attachments</h4>
                    @if(!empty($project->attachment_po_customer))
                        <div class="mb-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">PO Customer</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($project->attachment_po_customer as $attachment)
                                    <a href="{{ $attachment['url'] }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-xs hover:bg-indigo-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        {{ $attachment['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if(!empty($project->other_attachments))
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Other Attachments</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($project->other_attachments as $attachment)
                                    <a href="{{ $attachment['url'] }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 text-gray-700 rounded-lg text-xs hover:bg-gray-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        {{ $attachment['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

</div>
