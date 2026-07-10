<?php

namespace App\Mail;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TimesheetApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Timesheet $timesheet;
    public string $recipientName;
    public string $monthYear;
    public string $approvedAt;
    public string $statusLabel;
    public string $link;

    public function __construct(Timesheet $timesheet, string $recipientName)
    {
        $this->timesheet = $timesheet;
        $this->recipientName = $recipientName;
        $this->monthYear = \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . ' ' . $timesheet->year;
        $this->approvedAt = now()->format('d M Y, h:i A');
        $this->statusLabel = $timesheet->status_label;
        $this->link = route('records.timesheets');
    }

    public function build(): self
    {
        return $this->subject("Timesheet Approved - {$this->monthYear}")
            ->view('emails.timesheet.approved')
            ->with([
                'recipientName' => $this->recipientName,
                'monthYear' => $this->monthYear,
                'approvedAt' => $this->approvedAt,
                'statusLabel' => $this->statusLabel,
                'link' => $this->link,
            ]);
    }
}
