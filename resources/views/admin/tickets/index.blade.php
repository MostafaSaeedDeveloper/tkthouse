@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0">Tickets</h1>
        <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary">Create Ticket</a>
    </div>
    <div class="block block-rounded">
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead><tr><th>Name</th><th>Ticket #</th><th>Holder</th><th>QR</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->name }}</td>
                            <td>{{ $ticket->ticket_number ?? '-' }}</td>
                            <td>{{ $ticket->holder_name ?? '-' }}</td>
                            <td>
                                @if($ticket->qr_payload)
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={{ urlencode($ticket->qr_payload) }}" alt="QR" style="width:52px;height:52px;">
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ number_format($ticket->price,2) }}</td>
                            <td>{{ ucfirst($ticket->status) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.tickets.edit', $ticket) }}"><i class="fa fa-pen"></i></a>
                                <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-trash"></i></button></form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No tickets found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $tickets->links() }}</div>
</div>
@endsection
