Timesheet Pending Approval

Hello {{ $recipientName }},

A timesheet has been submitted by {{ $staffName }} and is pending your approval.

Staff: {{ $staffName }}
Month / Year: {{ $monthYear }}
Submitted At: {{ $submittedAt }}
Status: {{ $statusLabel }}

Please review the timesheet by visiting:
{{ $link }}

This is an automated message from {{ config('app.name') }}.
