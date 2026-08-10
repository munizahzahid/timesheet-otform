<?php

namespace App\Services;

use App\Mail\TimesheetApprovedMail;
use App\Mail\TimesheetRejectedMail;
use App\Mail\TimesheetReminderMail;
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

        Log::info("Sending timesheet submission email", [
            'timesheet_id' => $timesheet->id,
            'recipient_email' => $approver->email,
        ]);

        $monthYear = \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . ' ' . $timesheet->year;
        $submittedAt = $timesheet->submitted_at ? $timesheet->submitted_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $link = route('approvals.timesheets.show', $timesheet);

        $message = "Timesheet Pending Approval\n\n";
        $message .= "Hello {$approver->name},\n\n";
        $message .= "A timesheet has been submitted by {$timesheet->user->name} and is pending your approval.\n\n";
        $message .= "Staff: {$timesheet->user->name}\n";
        $message .= "Month / Year: {$monthYear}\n";
        $message .= "Submitted At: {$submittedAt}\n";
        $message .= "Status: {$timesheet->status_label}\n\n";
        $message .= "Please review the timesheet by visiting:\n";
        $message .= "{$link}\n\n";
        $message .= "This is an automated message from " . config('app.name') . ".";

        try {
            Mail::to($approver->email)->raw($message, function ($mail) use ($monthYear) {
                $mail->subject("Timesheet Pending Approval - {$monthYear}");
            });
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
        if (empty($user->email)) {
            Log::warning("Cannot send timesheet email to user #{$user->id}: missing email address.");
            return false;
        }

        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Cannot send timesheet email to user #{$user->id}: invalid email '{$user->email}'.");
            return false;
        }

        return true;
    }
}
