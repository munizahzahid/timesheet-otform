@extends('emails.ot.layout')

@section('content')
@php
$monthName = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
@endphp

<p>Hi {{ $approverName }},</p>

<p>An OT Form has been submitted and is pending your approval.</p>

<div class="details">
    <strong>Staff:</strong> {{ $otForm->user->name }}<br>
    <strong>Month:</strong> {{ $monthName }} {{ $otForm->year }}<br>
    <strong>Type:</strong> {{ $otForm->form_type_label }}<br>
    <strong>Company:</strong> {{ $otForm->company_name }}
</div>

<p>Please review the OT Form at your earliest convenience.</p>

<p style="text-align: center; margin-top: 30px;">
    <a href="{{ route('approvals.ot-forms.show', $otForm) }}" class="btn">Review OT Form</a>
</p>

<p>Thank you.</p>
@endsection
