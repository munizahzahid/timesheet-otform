@php
    $kanbanTasks = $tasks->map(function ($task) use ($project, $effectiveDates) {
        $effective = $effectiveDates[$task->id] ?? [];
        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'status' => $task->status ?? 'not_started',
            'remarks' => $task->remarks,
            'progress_actual' => $task->progress_actual,
            'weight' => $task->weight,
            'end_date_revise' => $task->end_date_revise?->format('Y-m-d'),
            'end_date_revise_formatted' => $task->end_date_revise?->format('M d'),
            'end_date_plan_formatted' => $task->end_date_plan?->format('M d'),
            'phase_id' => $task->phase_id,
            'phase_name' => $task->phase?->phase_name,
            'assigned_to_name' => $task->assignedTo?->name,
            'assigned_to_url' => $task->assignedTo ? route('admin.project.assigned-tasks', $task->assignedTo) : null,
            'comments_count' => $task->comments_count,
            'attachments_count' => $task->attachments_count,
            'plan_delay_days' => $effective['plan_delay_days'] ?? 0,
            'comments' => $task->comments->map(function ($comment) use ($project, $task) {
                return [
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'user_name' => $comment->user?->name,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at->diffForHumans(),
                    'is_owner' => $comment->user_id === auth()->id(),
                    'delete_url' => route('admin.project.projects.tasks.comments.destroy', [$project, $task, $comment]),
                ];
            })->values()->toArray(),
            'attachments' => $task->attachments->map(function ($attachment) use ($project, $task) {
                return [
                    'id' => $attachment->id,
                    'user_id' => $attachment->user_id,
                    'file_name' => $attachment->file_name,
                    'show_url' => route('admin.project.projects.tasks.attachments.show', [$project, $task, $attachment]),
                    'is_owner' => $attachment->user_id === auth()->id(),
                    'delete_url' => route('admin.project.projects.tasks.attachments.destroy', [$project, $task, $attachment]),
                ];
            })->values()->toArray(),
            'update_url' => route('admin.project.projects.tasks.inline-update', [$project, $task]),
            'show_url' => route('admin.project.projects.tasks.show', [$project, $task]),
            'edit_url' => route('admin.project.projects.tasks.edit', [$project, $task]),
            'delete_url' => route('admin.project.projects.tasks.destroy', [$project, $task]),
            'comment_store_url' => route('admin.project.projects.tasks.comments.store', [$project, $task]) . '?' . (request()->getQueryString() ?: 'tab=tasks'),
            'attachment_store_url' => route('admin.project.projects.tasks.attachments.store', [$project, $task]) . '?' . (request()->getQueryString() ?: 'tab=tasks'),
        ];
    })->values()->toArray();

    $kanbanProps = [
        'tasks' => $kanbanTasks,
        'csrfToken' => csrf_token(),
        'addTaskUrl' => route('admin.project.projects.tasks.create', $project),
    ];
@endphp

<script type="application/json" id="project-kanban-props">@json($kanbanProps)</script>
<div id="project-kanban"></div>
