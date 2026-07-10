<?php

namespace App\Mail;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TimesheetRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Timesheet $timesheet;
    public string $recipientName;
    public string $monthYear;
    public string $rejectedBy;
    public string $rejectedAt;
    public string $statusLabel;
    public string $remarks;
    public string $link;

    public function __construct(Timesheet $timesheet, string $recipientName, string $rejectedBy, string $remarks)
    {
        $this->timesheet = $timesheet;
        $this->recipientName = $recipientName;
        $this->monthYear = \DateTime::createFromFormat('!m', $timesheet->month)->format('F') . ' ' . $timesheet->year;
        $this->rejectedBy = $rejectedBy;
        $this->rejectedAt = now()->format('d M Y, h:i A');
        $this->statusLabel = $timesheet->status_label;
        $this->remarks = $remarks;
        $this->link = route('records.timesheets');
    }

    public function build(): self
    {
        return $this->subject("Timesheet Rejected - {$this->monthYear}")
            ->view('emails.timesheet.rejected')
            ->with([
                'recipientName' => $this->recipientName,
                'monthYear' => $this->monthYear,
                'rejectedBy' => $this->rejectedBy,
                'rejectedAt' => $this->rejectedAt,
                'statusLabel' => $this->statusLabel,
                'remarks' => $this->remarks,
                'link' => $this->link,
            ]);
    }
}
