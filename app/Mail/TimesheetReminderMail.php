<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TimesheetReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $recipientName;
    public string $monthYear;
    public string $deadline;
    public string $link;

    public function __construct(User $user, int $month, int $year, string $deadline)
    {
        $this->user = $user;
        $this->recipientName = $user->name;
        $this->monthYear = \DateTime::createFromFormat('!m', $month)->format('F') . ' ' . $year;
        $this->deadline = $deadline;
        $this->link = route('records.timesheets');
    }

    public function build(): self
    {
        return $this->subject("Timesheet Submission Reminder - {$this->monthYear}")
            ->view('emails.timesheet.reminder')
            ->with([
                'recipientName' => $this->recipientName,
                'monthYear' => $this->monthYear,
                'deadline' => $this->deadline,
                'link' => $this->link,
            ]);
    }
}
