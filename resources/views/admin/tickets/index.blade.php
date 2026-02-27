@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0">Tickets</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tickets.scanner') }}" class="btn btn-alt-info">QR Scanner</a>
            <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary">Create Ticket</a>
        </div>
    </div>
    <div class="block block-rounded">
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Ticket #</th>
                            <th>Order #</th>
                            <th>Holder</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($statusLabels = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
                        @forelse($tickets as $ticket)
                            <tr>
                                <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->name }}</a></td>
                                <td>{{ $ticket->ticket_number ?? '-' }}</td>
                                <td>{{ $ticket->order?->order_number ?? '-' }}</td>
                                <td>{{ $ticket->holder_name ?: '-' }}</td>
                                <td>{{ $statusLabels[$ticket->status] ?? str($ticket->status)->headline() }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('admin.tickets.show', $ticket) }}"><i class="fa fa-eye"></i></a>
                                    <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.tickets.edit', $ticket) }}"><i class="fa fa-pen"></i></a>
                                    <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No tickets found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $tickets->links() }}</div>
</div>
@endsection
