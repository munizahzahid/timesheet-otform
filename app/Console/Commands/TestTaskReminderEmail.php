<?php

namespace App\Console\Commands;

use App\Mail\SubtaskStartReminder;
use App\Models\ProjectTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestTaskReminderEmail extends Command
{
    protected $signature = 'app:test-task-reminder-email {email}';
    protected $description = 'Send a test task reminder email to specified address';

    public function handle()
    {
        $email = $this->argument('email');

        // Get a sample task
        $task = ProjectTask::with(['project', 'phase'])->first();

        if (!$task) {
            $this->error('No tasks found in database.');
            return;
        }

        $phaseName = $task->phase ? $task->phase->phase_name : '';
        $mail = new SubtaskStartReminder($task, $task->project->project_name, $phaseName);

        $this->info("Sending test email to: {$email}");
        $this->info("Task: {$task->task_name}");
        $this->info("Project: {$task->project->project_name}");

        Mail::to($email)->send($mail);

        $this->info('Test email sent successfully!');
    }
}
