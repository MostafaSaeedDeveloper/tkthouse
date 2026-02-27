@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="block block-rounded">
        <div class="block-header block-header-default d-flex justify-content-between">
            <h3 class="block-title">Edit Ticket #{{ $ticket->ticket_number ?? 'N/A' }}</h3>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-alt-info btn-sm">View</a>
                <a href="{{ route('admin.tickets.download', $ticket) }}" class="btn btn-alt-success btn-sm">PDF</a>
            </div>
        </div>
        <div class="block-content">
            <div class="mb-3 text-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR">
            </div>
            <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">
                @method('PUT')
                @include('admin.tickets.form')
            </form>
        </div>
    </div>
</div>
@endsection
