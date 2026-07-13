<?php

namespace App\Mail;

use App\Models\OtForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public OtForm $otForm;
    public string $staffName;
    public string $rejectorName;
    public string $remarks;

    public function __construct(OtForm $otForm, string $staffName, string $rejectorName, string $remarks)
    {
        $this->otForm = $otForm;
        $this->staffName = $staffName;
        $this->rejectorName = $rejectorName;
        $this->remarks = $remarks;
    }

    public function build(): self
    {
        $monthName = \DateTime::createFromFormat('!m', $this->otForm->month)->format('F');

        return $this->subject("OT Form Rejected - {$monthName} {$this->otForm->year}")
            ->markdown('emails.ot.rejected');
    }
}
