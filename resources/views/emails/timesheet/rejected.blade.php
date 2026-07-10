@extends('emails.timesheet.layout')

@section('content')
    <h2>Timesheet Rejected</h2>
    <p>Hello {{ $recipientName }},</p>
    <p>Your timesheet for <strong>{{ $monthYear }}</strong> has been rejected. Please review the remarks below and resubmit after making the necessary corrections.</p>

    <div class="details">
        <table>
            <tr>
                <td>Month / Year</td>
                <td><strong>{{ $monthYear }}</strong></td>
            </tr>
            <tr>
                <td>Rejected By</td>
                <td><strong>{{ $rejectedBy }}</strong></td>
            </tr>
            <tr>
                <td>Rejected At</td>
                <td><strong>{{ $rejectedAt }}</strong></td>
            </tr>
            <tr>
                <td>Status</td>
                <td><strong>{{ $statusLabel }}</strong></td>
            </tr>
        </table>
    </div>

    <div style="background-color: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 15px 0;">
        <h3 style="margin-top: 0; color: #c53030;">Remarks</h3>
        <p style="margin-bottom: 0;">{{ $remarks }}</p>
    </div>

    <p>Please update your timesheet and resubmit:</p>

    <a href="{{ $link }}" class="button">Edit Timesheet</a>
@endsection
