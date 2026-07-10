@extends('emails.timesheet.layout')

@section('content')
    <h2>Timesheet Pending Approval</h2>
    <p>Hello {{ $recipientName }},</p>
    <p>A timesheet has been submitted by <strong>{{ $staffName }}</strong> and is pending your approval.</p>

    <div class="details">
        <table>
            <tr>
                <td>Staff</td>
                <td><strong>{{ $staffName }}</strong></td>
            </tr>
            <tr>
                <td>Month / Year</td>
                <td><strong>{{ $monthYear }}</strong></td>
            </tr>
            <tr>
                <td>Submitted At</td>
                <td><strong>{{ $submittedAt }}</strong></td>
            </tr>
            <tr>
                <td>Status</td>
                <td><strong>{{ $statusLabel }}</strong></td>
            </tr>
        </table>
    </div>

    <p>Please review the timesheet by clicking the button below:</p>

    <a href="{{ $link }}" class="button">Review Timesheet</a>
@endsection
