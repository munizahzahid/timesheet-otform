<?php

namespace App\Mail;

use App\Models\OtForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public OtForm $otForm;
    public string $staffName;

    public function __construct(OtForm $otForm, string $staffName)
    {
        $this->otForm = $otForm;
        $this->staffName = $staffName;
    }

    public function build(): self
    {
        $monthName = \DateTime::createFromFormat('!m', $this->otForm->month)->format('F');

        return $this->subject("OT Form Approved - {$monthName} {$this->otForm->year}")
            ->markdown('emails.ot.approved');
    }
}
