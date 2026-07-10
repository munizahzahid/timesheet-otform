<?php

namespace App\Services;

use App\Mail\TimesheetApprovedMail;
use App\Mail\TimesheetRejectedMail;
use App\Mail\TimesheetReminderMail;
use App\Mail\TimesheetSubmittedMail;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TimesheetEmailNotificationService
{
    public function sendSubmissionNotification(Timesheet $timesheet, User $approver): void
    {
        if (!$this->canSendEmail($approver)) {
            return;
        }

        try {
            Mail::to($approver->email)->send(new TimesheetSubmittedMail($timesheet, $approver->name));
        } catch (\Exception $e) {
            Log::error("Failed to send timesheet submission email: {$e->getMessage()}", [
                'timesheet_id' => $timesheet->id,
                'recipient_id' => $approver->id,
            ]);
        }
    }

    public function sendApprovalNotification(Timesheet $timesheet): void
    {
        $recipient = $timesheet->user;

        if (!$this->canSendEmail($recipient)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new TimesheetApprovedMail($timesheet, $recipient->name));
        } catch (\Exception $e) {
            Log::error("Failed to send timesheet approval email: {$e->getMessage()}", [
                'timesheet_id' => $timesheet->id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    public function sendRejectionNotification(Timesheet $timesheet, User $rejector, string $remarks): void
    {
        $recipient = $timesheet->user;

        if (!$this->canSendEmail($recipient)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new TimesheetRejectedMail($timesheet, $recipient->name, $rejector->name, $remarks));
        } catch (\Exception $e) {
            Log::error("Failed to send timesheet rejection email: {$e->getMessage()}", [
                'timesheet_id' => $timesheet->id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    public function sendReminderNotification(User $user, int $month, int $year, string $deadline): void
    {
        if (!$this->canSendEmail($user)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new TimesheetReminderMail($user, $month, $year, $deadline));
        } catch (\Exception $e) {
            Log::error("Failed to send timesheet reminder email: {$e->getMessage()}", [
                'user_id' => $user->id,
                'month' => $month,
                'year' => $year,
            ]);
        }
    }

    private function canSendEmail(User $user): bool
    {
        return !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }
}
