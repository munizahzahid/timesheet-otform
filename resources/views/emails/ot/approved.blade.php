@extends('emails.ot.layout')

@section('content')
@php
$monthName = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
@endphp

<p>Hi {{ $staffName }},</p>

<p>Your OT Form has been approved.</p>

<div class="details">
    <strong>Month:</strong> {{ $monthName }} {{ $otForm->year }}<br>
    <strong>Type:</strong> {{ $otForm->form_type_label }}<br>
    <strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $otForm->status)) }}
</div>

<p>You can view your approved OT Form below.</p>

<p style="text-align: center; margin-top: 30px;">
    <a href="{{ route('ot-forms.edit', $otForm) }}" class="btn">View OT Form</a>
</p>

<p>Thank you.</p>
@endsection
