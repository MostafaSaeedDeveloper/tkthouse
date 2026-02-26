@extends('admin.master')
@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Tickets</h1>
        <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary"><i class="fa fa-plus me-1"></i>Create Ticket</a>
    </div>

    <div class="block block-rounded">
        <div class="block-content p-0">
            <table class="table table-hover table-vcenter mb-0">
                <thead><tr><th>Code</th><th>Ticket</th><th>Event</th><th>Customer</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->ticket_code }}</td><td>{{ $ticket->ticket_name }}</td><td>{{ $ticket->event?->name }}</td><td>{{ $ticket->customer?->full_name }}</td><td>{{ ucfirst($ticket->status) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-alt-info" href="{{ route('admin.tickets.show', $ticket) }}"><i class="fa fa-eye"></i></a>
                            <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.tickets.edit', $ticket) }}"><i class="fa fa-pen"></i></a>
                            <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-alt-danger"><i class="fa fa-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No tickets found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $tickets->links() }}</div>
</div>
@endsection
