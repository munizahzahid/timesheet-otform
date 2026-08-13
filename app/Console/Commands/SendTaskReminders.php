<?php

namespace App\Console\Commands;

use App\Mail\SubtaskDeadlineAlert;
use App\Mail\SubtaskDueWarning;
use App\Mail\SubtaskStartReminder;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskEmailLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskReminders extends Command
{
    protected $signature = 'project:send-task-reminders';
    protected $description = 'Send email notifications for subtask start reminders, due warnings, and deadline alerts';

    public function handle()
    {
        $today = Carbon::today();

        $this->info('Checking for task reminders...');
        $totalSent = 0;

        // 1. Start Reminder: tasks starting today
        $startReminderTasks = ProjectTask::with(['project', 'phase', 'assignedTo'])
            ->where('start_date_plan', $today)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($startReminderTasks as $task) {
            $this->sendStartReminder($task, $today);
            $totalSent++;
        }

        // 2. Due Warning: tasks at 90% plan progress
        $dueWarningTasks = ProjectTask::with(['project', 'phase', 'assignedTo'])
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'not_started')
            ->where('start_date_plan', '<=', $today)
            ->where('end_date_plan', '>', $today)
            ->get();

        foreach ($dueWarningTasks as $task) {
            $planProgress = $this->calculatePlanProgress($task);
            if ($planProgress >= 90) {
                $this->sendDueWarning($task, $planProgress);
                $totalSent++;
            }
        }

        // 3. Deadline Alert: today is plan end date
        $deadlineTasks = ProjectTask::with(['project', 'phase', 'assignedTo'])
            ->where('end_date_plan', $today)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($deadlineTasks as $task) {
            $this->sendDeadlineAlert($task);
            $totalSent++;
        }

        $this->info("Task reminders sent: {$totalSent}");
    }

    private function sendStartReminder(ProjectTask $task, Carbon $date)
    {
        // Check if already sent today
        $alreadySent = ProjectTaskEmailLog::where('project_task_id', $task->id)
            ->where('email_type', 'start_reminder')
            ->where('sent_at', '>=', Carbon::today()->startOfDay())
            ->exists();

        if ($alreadySent) {
            return;
        }

        $recipients = $this->getRecipients($task);
        if (empty($recipients)) {
            return;
        }

        $phaseName = $task->phase ? $task->phase->phase_name : '';
        $mail = new SubtaskStartReminder($task, $task->project->project_name, $phaseName);

        Mail::to($recipients['to'])
            ->cc($recipients['cc'])
            ->send($mail);

        // Log
        ProjectTaskEmailLog::create([
            'project_task_id' => $task->id,
            'email_type' => 'start_reminder',
            'sent_at' => now(),
            'recipients' => $recipients,
        ]);

        $this->info("Start reminder sent for task: {$task->task_name}");
    }

    private function sendDueWarning(ProjectTask $task, int $planProgress)
    {
        // Check if already sent
        $alreadySent = ProjectTaskEmailLog::where('project_task_id', $task->id)
            ->where('email_type', 'due_warning')
            ->exists();

        if ($alreadySent) {
            return;
        }

        $recipients = $this->getRecipients($task);
        if (empty($recipients)) {
            return;
        }

        $phaseName = $task->phase ? $task->phase->phase_name : '';
        $mail = new SubtaskDueWarning($task, $task->project->project_name, $phaseName, $planProgress);

        Mail::to($recipients['to'])
            ->cc($recipients['cc'])
            ->send($mail);

        // Log
        ProjectTaskEmailLog::create([
            'project_task_id' => $task->id,
            'email_type' => 'due_warning',
            'sent_at' => now(),
            'recipients' => $recipients,
        ]);

        $this->info("Due warning sent for task: {$task->task_name}");
    }

    private function sendDeadlineAlert(ProjectTask $task)
    {
        // Check if already sent
        $alreadySent = ProjectTaskEmailLog::where('project_task_id', $task->id)
            ->where('email_type', 'deadline_alert')
            ->where('sent_at', '>=', Carbon::today()->startOfDay())
            ->exists();

        if ($alreadySent) {
            return;
        }

        $recipients = $this->getRecipients($task);
        if (empty($recipients)) {
            return;
        }

        $phaseName = $task->phase ? $task->phase->phase_name : '';
        $mail = new SubtaskDeadlineAlert($task, $task->project->project_name, $phaseName);

        Mail::to($recipients['to'])
            ->cc($recipients['cc'])
            ->send($mail);

        // Log
        ProjectTaskEmailLog::create([
            'project_task_id' => $task->id,
            'email_type' => 'deadline_alert',
            'sent_at' => now(),
            'recipients' => $recipients,
        ]);

        $this->info("Deadline alert sent for task: {$task->task_name}");
    }

    private function getRecipients(ProjectTask $task): array
    {
        $to = [];
        $cc = [];

        // Get assigned user
        if ($task->assignedTo && $task->assignedTo->email) {
            $to[] = $task->assignedTo->email;
        }

        // Get project manager
        $manager = $this->getUserByStaffId($task->project->project_manager_staff_id);
        if ($manager && $manager->email) {
            if (empty($to)) {
                $to[] = $manager->email;
            } else {
                $cc[] = $manager->email;
            }
        }

        // Get deskmen
        $deskman1 = $this->getUserByStaffId($task->project->deskman_1_staff_id);
        if ($deskman1 && $deskman1->email) {
            $cc[] = $deskman1->email;
        }

        $deskman2 = $this->getUserByStaffId($task->project->deskman_2_staff_id);
        if ($deskman2 && $deskman2->email) {
            $cc[] = $deskman2->email;
        }

        // Remove duplicates
        $cc = array_unique($cc);

        return [
            'to' => $to,
            'cc' => $cc,
        ];
    }

    private function getUserByStaffId(?string $staffId): ?User
    {
        if (!$staffId) {
            return null;
        }
        return User::where('staff_no', $staffId)->first();
    }

    private function calculatePlanProgress(ProjectTask $task): int
    {
        if (!$task->start_date_plan || !$task->end_date_plan) {
            return 0;
        }

        $today = Carbon::today();
        $start = $task->start_date_plan->copy()->startOfDay();
        $end = $task->end_date_plan->copy()->startOfDay();

        if ($today->lte($start)) {
            return 0;
        }

        if ($today->gte($end)) {
            return 100;
        }

        $totalDays = $start->diffInDays($end);
        if ($totalDays <= 0) {
            return 100;
        }

        $elapsed = $start->diffInDays($today);
        return (int) round(($elapsed / $totalDays) * 100);
    }
}
