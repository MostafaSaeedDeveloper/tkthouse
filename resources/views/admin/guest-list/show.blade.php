@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Guest Invitation #{{ $ticket->ticket_number }}</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.guest-list.edit', $ticket) }}" class="btn btn-alt-primary">Edit</a>
            <a href="{{ route('admin.guest-list.index') }}" class="btn btn-alt-secondary">Back</a>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-content">
            <p><strong>Event:</strong> {{ $ticket->event?->name ?: '-' }}</p>
            <p><strong>Type:</strong> Guest List Invitation</p>
            <p><strong>Name:</strong> {{ $ticket->holder_name ?: '-' }}</p>
            <p><strong>Email:</strong> {{ $ticket->holder_email ?: '-' }}</p>
            <p><strong>Status:</strong> {{ str($ticket->status)->replace('_', ' ')->title() }}</p>
            <p><strong>Issued At:</strong> {{ $ticket->issued_at?->format('Y-m-d H:i') ?: '-' }}</p>
            <p><strong>Description:</strong><br>{{ $ticket->description ?: '-' }}</p>
            <hr>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR">
        </div>
    </div>
</div>
@endsection
