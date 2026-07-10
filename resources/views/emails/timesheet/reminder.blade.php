@extends('emails.timesheet.layout')

@section('content')
    <h2>Timesheet Submission Reminder</h2>
    <p>Hello {{ $recipientName }},</p>
    <p>This is a friendly reminder to submit your timesheet for <strong>{{ $monthYear }}</strong>.</p>

    <div class="details">
        <table>
            <tr>
                <td>Month / Year</td>
                <td><strong>{{ $monthYear }}</strong></td>
            </tr>
            <tr>
                <td>Deadline</td>
                <td><strong>{{ $deadline }}</strong></td>
            </tr>
        </table>
    </div>

    <p>Please ensure your timesheet is submitted on time to avoid any delays in processing.</p>

    <a href="{{ $link }}" class="button">Submit Timesheet</a>
@endsection
