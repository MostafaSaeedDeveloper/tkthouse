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

    <div class="block block-rounded mb-3">
        <div class="block-content">
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="tickets-search">Search</label>
                    <input type="text" id="tickets-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Ticket #, holder, email, or order #">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tickets-status">Status</label>
                    <select id="tickets-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach(['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tickets-event">Event</label>
                    <input type="text" id="tickets-event" name="event" class="form-control" value="{{ request('event') }}" placeholder="Event name">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('admin.tickets.index') }}" class="btn btn-alt-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Ticket Number</th>
                            <th>Holder</th>
                            <th>Event</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($statusLabels = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
                        @forelse($tickets as $ticket)
                            <tr>
                                <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number ?? '-' }}</a></td>
                                <td>{{ $ticket->holder_name ?: '-' }}</td>
                                <td>{{ $ticket->eventLabel() ?: '-' }}</td>
                                <td>{{ $ticket->order?->order_number ?? '-' }}</td>
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
