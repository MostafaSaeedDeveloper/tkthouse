@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Guest List Invitations</h2>
        @can('tickets.create')
            <a href="{{ route('admin.guest-list.create') }}" class="btn btn-primary"><i class="fa fa-plus me-1"></i>Create Guest List</a>
        @endcan
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Ticket #, name, email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select js-select2">
                        <option value="">All events</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        @foreach(['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.guest-list.index') }}">Reset</a>
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
                            <th>Email</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($statusLabels = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
                        @forelse($tickets as $ticket)
                            <tr>
                                <td><a href="{{ route('admin.guest-list.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                                <td>{{ $ticket->holder_name ?: '-' }}</td>
                                <td>{{ $ticket->holder_email ?: '-' }}</td>
                                <td>{{ $ticket->event?->name ?: $ticket->eventLabel() }}</td>
                                <td>{{ $statusLabels[$ticket->status] ?? str($ticket->status)->headline() }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('admin.guest-list.show', $ticket) }}"><i class="fa fa-eye"></i></a>
                                    @can('tickets.update')
                                        <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.guest-list.edit', $ticket) }}"><i class="fa fa-pen"></i></a>
                                    @endcan
                                    @can('tickets.delete')
                                        <form action="{{ route('admin.guest-list.destroy', $ticket) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No guest invitations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $tickets->links() }}</div>
</div>
@endsection
