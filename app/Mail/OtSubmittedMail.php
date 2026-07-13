<?php

namespace App\Mail;

use App\Models\OtForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public OtForm $otForm;
    public string $approverName;

    public function __construct(OtForm $otForm, string $approverName)
    {
        $this->otForm = $otForm;
        $this->approverName = $approverName;
    }

    public function build(): self
    {
        $monthName = \DateTime::createFromFormat('!m', $this->otForm->month)->format('F');

        return $this->subject("OT Form Pending Approval - {$this->otForm->user->name} ({$monthName} {$this->otForm->year})")
            ->markdown('emails.ot.submitted');
    }
}
