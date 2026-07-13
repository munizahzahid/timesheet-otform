@extends('emails.ot.layout')

@section('content')
@php
$monthName = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
@endphp

<p>Hi {{ $staffName }},</p>

<p>Your OT Form has been returned by HR for correction.</p>

<div class="details">
    <strong>Month:</strong> {{ $monthName }} {{ $otForm->year }}<br>
    <strong>Type:</strong> {{ $otForm->form_type_label }}<br>
    <strong>Remarks:</strong> {{ $remarks }}
</div>

<p>Please review the remarks and resubmit the OT Form after making the necessary corrections.</p>

<p style="text-align: center; margin-top: 30px;">
    <a href="{{ route('ot-forms.edit', $otForm) }}" class="btn">Edit OT Form</a>
</p>

<p>Thank you.</p>
@endsection
