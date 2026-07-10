<?php

namespace App\Mail;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TimesheetSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Timesheet $timesheet;
    public string $recipientName;
    public string $monthYear;
    public string $submittedAt;
    public string $statusLabel;
    public string $link;

    public function __construct(Timesheet $timesheet, string $recipientName)
    {
        $this->timesheet = $timesheet;
        $this->recipientName = $recipientName;
        $this->monthYear = \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . ' ' . $timesheet->year;
        $this->submittedAt = $timesheet->submitted_at ? $timesheet->submitted_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $this->statusLabel = $timesheet->status_label;
        $this->link = route('approvals.timesheets.show', $timesheet);
    }

    public function build(): self
    {
        return $this->subject("Timesheet Pending Approval - {$this->monthYear}")
            ->view('emails.timesheet.submitted')
            ->with([
                'recipientName' => $this->recipientName,
                'staffName' => $this->timesheet->user->name,
                'monthYear' => $this->monthYear,
                'submittedAt' => $this->submittedAt,
                'statusLabel' => $this->statusLabel,
                'link' => $this->link,
            ]);
    }
}
