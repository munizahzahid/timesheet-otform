<?php

namespace App\Console\Commands;

use App\Models\Timesheet;
use App\Models\User;
use App\Services\TimesheetEmailNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTimesheetReminders extends Command
{
    protected $signature = 'timesheet:send-reminders';

    protected $description = 'Send reminder emails to staff who have not submitted their timesheets for the current month';

    public function handle(TimesheetEmailNotificationService $emailService): int
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        // Find staff users (non-admin)
        $users = User::where('is_active', true)
            ->whereNotIn('role', ['admin'])
            ->get();

        $deadline = Carbon::now()->endOfMonth()->format('d M Y');
        $reminderCount = 0;

        foreach ($users as $user) {
            $submitted = Timesheet::where('user_id', $user->id)
                ->where('month', $month)
                ->where('year', $year)
                ->whereNotIn('status', ['draft'])
                ->exists();

            if (!$submitted) {
                $emailService->sendReminderNotification($user, $month, $year, $deadline);
                $reminderCount++;
            }
        }

        $this->info("Sent {$reminderCount} reminder email(s) for {$month}/{$year}.");

        return self::SUCCESS;
    }
}
