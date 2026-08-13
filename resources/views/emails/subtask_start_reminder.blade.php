<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Start Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #4F46E5, #3B82F6); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .content { padding: 30px; }
        .task-info { background-color: #f9fafb; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .task-info p { margin: 5px 0; }
        .task-info strong { color: #1f2937; }
        .task-info span { color: #6b7280; }
        .cta { text-align: center; margin-top: 30px; }
        .cta a { display: inline-block; background-color: #3B82F6; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: bold; }
        .cta a:hover { background-color: #2563EB; }
        .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Subtask Start Reminder</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>This is a reminder that your subtask is scheduled to start today:</p>
            
            <div class="task-info">
                <p><strong>Subtask:</strong> <span>{{ $task->task_name }}</span></p>
                <p><strong>Project:</strong> <span>{{ $projectName }}</span></p>
                @if($phaseName)
                <p><strong>Task:</strong> <span>{{ $phaseName }}</span></p>
                @endif
                <p><strong>Planned Start Date:</strong> <span>{{ $task->start_date_plan ? $task->start_date_plan->format('F j, Y') : 'Not set' }}</span></p>
                <p><strong>Planned End Date:</strong> <span>{{ $task->end_date_plan ? $task->end_date_plan->format('F j, Y') : 'Not set' }}</span></p>
            </div>

            <p>Please ensure you are prepared to begin this task on time.</p>

            <div class="cta">
                <a href="{{ url('/admin/project/projects/' . $task->project_id) }}">View Task</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated notification from the Project Management System.</p>
        </div>
    </div>
</body>
</html>
