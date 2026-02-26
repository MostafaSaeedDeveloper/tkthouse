@extends('admin.master')
@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Ticket Details</h1>
        <a href="{{ route('admin.tickets.index') }}" class="btn btn-alt-secondary btn-sm">Back</a>
    </div>
    <div class="block block-rounded"><div class="block-content">
        <p><strong>Code:</strong> {{ $ticket->ticket_code }}</p>
        <p><strong>Name:</strong> {{ $ticket->ticket_name }}</p>
        <p><strong>Event:</strong> {{ $ticket->event?->name }}</p>
        <p><strong>Customer:</strong> {{ $ticket->customer?->full_name }}</p>
        <p><strong>Order:</strong> {{ $ticket->order?->order_number }}</p>
        <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
    </div></div>
</div>
@endsection
