<?php

namespace App\Services;

use App\Mail\OtApprovedMail;
use App\Mail\OtHrReturnedMail;
use App\Mail\OtRejectedMail;
use App\Mail\OtSubmittedMail;
use App\Models\OtForm;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtEmailNotificationService
{
    public function sendSubmissionNotification(OtForm $otForm, User $approver): void
    {
        if (!$this->canSendEmail($approver)) {
            return;
        }

        try {
            Mail::to($approver->email)->send(new OtSubmittedMail($otForm, $approver->name));
        } catch (\Exception $e) {
            Log::error("Failed to send OT submission email: {$e->getMessage()}", [
                'ot_form_id' => $otForm->id,
                'recipient_id' => $approver->id,
            ]);
        }
    }

    public function sendApprovalNotification(OtForm $otForm): void
    {
        $recipient = $otForm->user;

        if (!$this->canSendEmail($recipient)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new OtApprovedMail($otForm, $recipient->name));
        } catch (\Exception $e) {
            Log::error("Failed to send OT approval email: {$e->getMessage()}", [
                'ot_form_id' => $otForm->id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    public function sendRejectionNotification(OtForm $otForm, User $rejector, string $remarks): void
    {
        $recipient = $otForm->user;

        if (!$this->canSendEmail($recipient)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new OtRejectedMail($otForm, $recipient->name, $rejector->name, $remarks));
        } catch (\Exception $e) {
            Log::error("Failed to send OT rejection email: {$e->getMessage()}", [
                'ot_form_id' => $otForm->id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    public function sendHrReturnNotification(OtForm $otForm, string $remarks): void
    {
        $recipient = $otForm->user;

        if (!$this->canSendEmail($recipient)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new OtHrReturnedMail($otForm, $recipient->name, $remarks));
        } catch (\Exception $e) {
            Log::error("Failed to send OT HR return email: {$e->getMessage()}", [
                'ot_form_id' => $otForm->id,
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    public function sendBulkSubmissionNotification(OtForm $otForm, iterable $recipients): void
    {
        foreach ($recipients as $recipient) {
            $this->sendSubmissionNotification($otForm, $recipient);
        }
    }

    private function canSendEmail(User $user): bool
    {
        return !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }
}
