@php
    $statusOptions = [
        '' => '— Select Status —',
        'active' => 'Active',
        'completed' => 'Completed',
        'delayed' => 'Delayed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    $projectData = $project->toArray();
    foreach (['start_date_plan', 'end_date_plan', 'start_date_actual', 'end_date_actual', 'start_date_revise', 'end_date_revise'] as $df) {
        $projectData[$df] = $project->{$df}?->format('Y-m-d') ?? '';
    }

    $staffData = $staffList->map(function ($staff) {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'staff_no' => $staff->staff_no,
            'department' => $staff->department?->name,
        ];
    })->values()->toArray();

    $redirectUrl = request()->input('redirect') ?? route('admin.project.projects.show', ['project' => $project, 'tab' => 'details']);

    $formProps = [
        'project' => $projectData,
        'staffList' => $staffData,
        'statusOptions' => $statusOptions,
        'csrfToken' => csrf_token(),
        'updateUrl' => route('admin.project.projects.update', $project),
        'cancelUrl' => $redirectUrl,
        'redirectUrl' => route('admin.project.projects.show', ['project' => $project, 'tab' => 'details']),
    ];
@endphp

<script type="application/json" id="project-details-form-props">@json($formProps)</script>
<div id="project-details-form"></div>
