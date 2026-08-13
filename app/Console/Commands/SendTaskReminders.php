<?php

namespace App\Console\Commands;

use App\Mail\SubtaskDeadlineAlert;
use App\Mail\SubtaskDueWarning;
use App\Mail\SubtaskStartReminder;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskEmailLog;
use App\Models\User;
use App\Services\TelegramMessageTemplates;
use App\Services\TelegramNotificationService;
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

        // Send Telegram notification
        $this->sendTelegramNotification($task, 'start_reminder', $recipients);

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

        // Send Telegram notification
        $this->sendTelegramNotification($task, 'due_warning', $recipients);

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

        // Send Telegram notification
        $this->sendTelegramNotification($task, 'deadline_alert', $recipients);

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

    private function sendTelegramNotification(ProjectTask $task, string $notificationType, array $emailRecipients): void
    {
        $telegram = new TelegramNotificationService();
        if (!$telegram->isConfigured()) {
            Log::warning('Telegram bot token not configured, skipping Telegram notification');
            return;
        }

        // Get Telegram chat IDs from recipients
        $telegramChatIds = $this->getTelegramChatIds($task, $emailRecipients);
        if (empty($telegramChatIds)) {
            Log::info('No Telegram chat IDs found for task: ' . $task->task_name);
            return;
        }

        // Format message based on notification type
        $phaseName = $task->phase ? $task->phase->phase_name : '';
        $data = [
            'subtask_name' => $task->task_name,
            'project_name' => $task->project->project_name,
            'task_name' => $phaseName,
            'start_date' => $task->start_date_plan ? $task->start_date_plan->format('F j, Y') : 'Not set',
            'end_date' => $task->end_date_plan ? $task->end_date_plan->format('F j, Y') : 'Not set',
            'status' => ucfirst(str_replace('_', ' ', $task->status)),
            'actual_progress' => $task->progress_actual,
            'plan_progress' => $this->calculatePlanProgress($task),
            'url' => url('/project/projects/' . $task->project_id),
        ];

        switch ($notificationType) {
            case 'start_reminder':
                $message = TelegramMessageTemplates::subtaskStartReminder($data);
                break;
            case 'due_warning':
                $message = TelegramMessageTemplates::subtaskDueWarning($data);
                break;
            case 'deadline_alert':
                $message = TelegramMessageTemplates::subtaskDeadlineAlert($data);
                break;
            default:
                return;
        }

        // Send to all chat IDs
        $results = $telegram->sendMessageToMultiple($telegramChatIds, $message);
        $successCount = count(array_filter($results, fn($r) => $r === true));
        Log::info("Telegram notification sent to {$successCount} recipients for task: {$task->task_name}");
    }

    private function getTelegramChatIds(ProjectTask $task, array $emailRecipients): array
    {
        $chatIds = [];

        // Get assigned user's chat ID
        if ($task->assignedTo && $task->assignedTo->telegram_chat_id) {
            $chatIds[] = $task->assignedTo->telegram_chat_id;
        }

        // Get manager's chat ID
        $manager = $this->getUserByStaffId($task->project->project_manager_staff_id);
        if ($manager && $manager->telegram_chat_id) {
            if (empty($chatIds)) {
                $chatIds[] = $manager->telegram_chat_id;
            } else {
                $chatIds[] = $manager->telegram_chat_id;
            }
        }

        // Get deskmen's chat IDs
        $deskman1 = $this->getUserByStaffId($task->project->deskman_1_staff_id);
        if ($deskman1 && $deskman1->telegram_chat_id) {
            $chatIds[] = $deskman1->telegram_chat_id;
        }

        $deskman2 = $this->getUserByStaffId($task->project->deskman_2_staff_id);
        if ($deskman2 && $deskman2->telegram_chat_id) {
            $chatIds[] = $deskman2->telegram_chat_id;
        }

        // Remove duplicates
        return array_unique($chatIds);
    }
}
