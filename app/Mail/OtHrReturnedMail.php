<?php

namespace App\Mail;

use App\Models\OtForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtHrReturnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public OtForm $otForm;
    public string $staffName;
    public string $remarks;

    public function __construct(OtForm $otForm, string $staffName, string $remarks)
    {
        $this->otForm = $otForm;
        $this->staffName = $staffName;
        $this->remarks = $remarks;
    }

    public function build(): self
    {
        $monthName = \DateTime::createFromFormat('!m', $this->otForm->month)->format('F');

        return $this->subject("OT Form Returned for Correction - {$monthName} {$this->otForm->year}")
            ->markdown('emails.ot.returned');
    }
}
