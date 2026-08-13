<?php

namespace App\Services;

class TelegramMessageTemplates
{
    /**
     * Timesheet pending approval
     */
    public static function timesheetPendingApproval(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
📋 Timesheet Pending Approval

<b>Month:</b> {$data['month']}
<b>Staff:</b> {$data['staff_name']}
<b>Total Hours:</b> {$data['total_hours']}

The timesheet is pending approval. Please review it.

<a href="{$url}">View Timesheet</a>
TEXT;
    }

    /**
     * Timesheet approved
     */
    public static function timesheetApproved(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
✅ Timesheet Approved

<b>Month:</b> {$data['month']}
<b>Staff:</b> {$data['staff_name']}

Your timesheet has been approved.

<a href="{$url}">View Timesheet</a>
TEXT;
    }

    /**
     * Timesheet rejected
     */
    public static function timesheetRejected(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
❌ Timesheet Rejected

<b>Month:</b> {$data['month']}
<b>Staff:</b> {$data['staff_name']}
<b>Reason:</b> {$data['reason']}

Your timesheet has been rejected. Please make corrections and resubmit.

<a href="{$url}">View Timesheet</a>
TEXT;
    }

    /**
     * Timesheet reminder
     */
    public static function timesheetReminder(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
⏰ Timesheet Reminder

<b>Month:</b> {$data['month']}
<b>Staff:</b> {$data['staff_name']}

Please submit your timesheet for this month if you haven't already.

<a href="{$url}">Submit Timesheet</a>
TEXT;
    }

    /**
     * OT Form pending approval
     */
    public static function otFormPendingApproval(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
📝 OT Form Pending Approval

<b>Date:</b> {$data['date']}
<b>Staff:</b> {$data['staff_name']}
<b>Hours:</b> {$data['hours']}
<b>Project:</b> {$data['project']}

Your OT form is pending approval.

<a href="{$url}">View OT Form</a>
TEXT;
    }

    /**
     * OT Form approved
     */
    public static function otFormApproved(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
✅ OT Form Approved

<b>Date:</b> {$data['date']}
<b>Staff:</b> {$data['staff_name']}
<b>Hours:</b> {$data['hours']}

Your OT form has been approved.

<a href="{$url}">View OT Form</a>
TEXT;
    }

    /**
     * OT Form rejected
     */
    public static function otFormRejected(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
❌ OT Form Rejected

<b>Date:</b> {$data['date']}
<b>Staff:</b> {$data['staff_name']}
<b>Reason:</b> {$data['reason']}

Your OT form has been rejected. Please make corrections and resubmit.

<a href="{$url}">View OT Form</a>
TEXT;
    }

    /**
     * OT Form reminder
     */
    public static function otFormReminder(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
⏰ OT Form Reminder

<b>Date:</b> {$data['date']}
<b>Staff:</b> {$data['staff_name']}

Please submit your OT form for this date if you haven't already.

<a href="{$url}">Submit OT Form</a>
TEXT;
    }

    /**
     * Subtask start reminder
     */
    public static function subtaskStartReminder(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
🔔 Subtask Start Reminder

<b>Subtask:</b> {$data['subtask_name']}
<b>Project:</b> {$data['project_name']}
<b>Task:</b> {$data['task_name']}
<b>Planned Start:</b> {$data['start_date']}
<b>Planned End:</b> {$data['end_date']}

This subtask is scheduled to start today. Please ensure you are prepared to begin.

<a href="{$url}">View Subtask</a>
TEXT;
    }

    /**
     * Subtask due warning (90% progress)
     */
    public static function subtaskDueWarning(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
⚠️ Subtask Due Warning

<b>Subtask:</b> {$data['subtask_name']}
<b>Project:</b> {$data['project_name']}
<b>Task:</b> {$data['task_name']}
<b>Planned Progress:</b> {$data['plan_progress']}%
<b>Planned End:</b> {$data['end_date']}

This subtask is 90% through its planned timeline and is approaching its due date. Please ensure you complete it on time.

<a href="{$url}">View Subtask</a>
TEXT;
    }

    /**
     * Subtask deadline alert
     */
    public static function subtaskDeadlineAlert(array $data): string
    {
        $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        return <<<TEXT
🚨 Subtask Deadline Alert

<b>Subtask:</b> {$data['subtask_name']}
<b>Project:</b> {$data['project_name']}
<b>Task:</b> {$data['task_name']}
<b>Planned End Date:</b> {$data['end_date']}
<b>Current Status:</b> {$data['status']}
<b>Actual Progress:</b> {$data['actual_progress']}%

This subtask's planned end date is today. Please ensure you complete it today or update its status if it has been completed.

<a href="{$url}">View Subtask</a>
TEXT;
    }
}
