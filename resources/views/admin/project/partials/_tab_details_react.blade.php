@php
    $statusLabels = [
        '' => '—',
        'active' => 'Active',
        'completed' => 'Completed',
        'delayed' => 'Delayed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    $projectData = $project->toArray();
    $projectData['created_by_name'] = $project->createdBy->name ?? 'System';
    $projectData['status_label'] = $statusLabels[$project->status ?? ''] ?? '—';

    foreach (['start_date_plan', 'end_date_plan', 'start_date_actual', 'end_date_actual', 'start_date_revise', 'end_date_revise'] as $df) {
        $projectData[$df . '_formatted'] = $project->{$df}?->format('M d, Y') ?? '—';
    }

    $projectData['phases'] = $project->phases->map(function ($phase) {
        $today = \Carbon\Carbon::today()->startOfDay();
        $reviseProgress = null;
        if ($phase->start_date_revise && $phase->end_date_revise) {
            $reviseStart = $phase->start_date_revise->copy()->startOfDay();
            $reviseEnd = $phase->end_date_revise->copy()->startOfDay();
            if ($today->lte($reviseStart)) {
                $reviseProgress = 0;
            } elseif ($today->gte($reviseEnd)) {
                $reviseProgress = 100;
            } else {
                $totalDays = $reviseStart->diffInDays($reviseEnd);
                $reviseProgress = $totalDays > 0 ? (int) round(($reviseStart->diffInDays($today) / $totalDays) * 100) : 100;
            }
        }
        return array_merge($phase->toArray(), [
            'revise_progress' => $reviseProgress,
        ]);
    })->values()->toArray();

    $projectData['attachment_po_customer'] = $project->attachment_po_customer ?? [];
    $projectData['other_attachments'] = $project->other_attachments ?? [];

    $detailsProps = [
        'project' => $projectData,
        'editUrl' => route('project.projects.show', ['project' => $project, 'edit' => 1, 'tab' => 'details']),
    ];
@endphp

<script type="application/json" id="project-details-props">@json($detailsProps)</script>
<div id="project-details"></div>
