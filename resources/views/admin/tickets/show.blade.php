@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    @php($statusLabels = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])

    <div class="block block-rounded">
        <div class="block-header block-header-default d-flex justify-content-between">
            <h3 class="block-title">Ticket #{{ $ticket->ticket_number ?? 'N/A' }}</h3>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.tickets.edit', $ticket) }}" class="btn btn-alt-primary btn-sm">Edit</a>
                <a href="{{ route('admin.tickets.download', $ticket) }}" class="btn btn-alt-success btn-sm">Download PDF</a>
                <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-alt-danger btn-sm" type="submit">Delete</button>
                </form>
            </div>
        </div>
        <div class="block-content">
            <div class="row g-3">
                <div class="col-md-8">
                    <p><strong>Ticket Number:</strong> {{ $ticket->ticket_number ?? "-" }}</p>
                    <p><strong>Holder:</strong> {{ $ticket->holder_name ?: "-" }}</p>
                    <p><strong>Event:</strong> {{ $ticket->eventLabel() ?: "-" }}</p>
                    <p><strong>Ticket Type:</strong> {{ $ticket->ticketTypeLabel() ?: "-" }}</p>
                    <p><strong>Order #:</strong> {{ $ticket->order?->order_number ?? '-' }}</p>
                                        <p><strong>Email:</strong> {{ $ticket->holder_email ?: '-' }}</p>
                    <p><strong>Phone:</strong> {{ $ticket->holder_phone ?: '-' }}</p>
                    <p><strong>Status:</strong> {{ $statusLabels[$ticket->status] ?? str($ticket->status)->headline() }}</p>
                    <p><strong>Created At:</strong> {{ $ticket->created_at?->format('Y-m-d H:i:s') ?: '-' }}</p>
                    <p><strong>Updated At:</strong> {{ $ticket->updated_at?->format('Y-m-d H:i:s') ?: '-' }}</p>
                    <p><strong>Issued At:</strong> {{ $ticket->issued_at?->format('Y-m-d H:i:s') ?: '-' }}</p>
                    <p><strong>Checked In At:</strong> {{ $ticket->checked_in_at?->format('Y-m-d H:i:s') ?: '-' }}</p>
                    <p><strong>Canceled At:</strong> {{ $ticket->canceled_at?->format('Y-m-d H:i:s') ?: '-' }}</p>
                </div>
                <div class="col-md-4 text-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR" class="img-fluid">
                </div>
            </div>

            <hr>
            <form method="POST" action="{{ route('admin.tickets.send-email', $ticket) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-7">
                    <label class="form-label">Send Ticket by Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $ticket->holder_email) }}" required>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Send Email</button>
                    <a href="{{ route('admin.tickets.send-whatsapp', $ticket) }}" class="btn btn-success">Send WhatsApp</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
