@extends('emails.timesheet.layout')

@section('content')
    <h2>Timesheet Approved</h2>
    <p>Hello {{ $recipientName }},</p>
    <p>Your timesheet for <strong>{{ $monthYear }}</strong> has been approved.</p>

    <div class="details">
        <table>
            <tr>
                <td>Month / Year</td>
                <td><strong>{{ $monthYear }}</strong></td>
            </tr>
            <tr>
                <td>Approved At</td>
                <td><strong>{{ $approvedAt }}</strong></td>
            </tr>
            <tr>
                <td>Status</td>
                <td><strong>{{ $statusLabel }}</strong></td>
            </tr>
        </table>
    </div>

    <p>You can view your approved timesheet by clicking the button below:</p>

    <a href="{{ $link }}" class="button">View Timesheet</a>
@endsection
