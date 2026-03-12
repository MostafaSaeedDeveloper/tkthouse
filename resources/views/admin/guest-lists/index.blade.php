@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0">Guest List</h1>
        <a href="{{ route('admin.guest-lists.create') }}" class="btn btn-primary">Create Invitations</a>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name / email / ticket #">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select">
                        <option value="">All events</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Invitation Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="sent" @selected(request('status') === 'sent')>Sent</option>
                        <option value="not_sent" @selected(request('status') === 'not_sent')>Not Sent</option>
                        <option value="scanned" @selected(request('status') === 'scanned')>Scanned</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.guest-lists.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-content p-0 table-responsive">
            <table class="table table-hover table-vcenter mb-0">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Event</th>
                        <th>Category</th>
                        <th>Invitation</th>
                        <th>Scan Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($guests as $guest)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $guest->holder_name }}</div>
                            <small class="text-muted">{{ $guest->holder_email ?: $guest->holder_phone ?: 'No contact' }}</small>
                        </td>
                        <td>{{ $guest->event?->name ?: $guest->eventLabel() }}</td>
                        <td>{{ $guest->guest_category ?: '-' }}</td>
                        <td>{{ $guest->invitation_sent_at ? 'Sent' : 'Not Sent' }}</td>
                        <td>{{ ['not_checked_in'=>'Not Scanned','checked_in'=>'Scanned','canceled'=>'Canceled'][$guest->status] ?? $guest->status }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.guest-lists.edit', $guest) }}"><i class="fa fa-pen"></i></a>
                            <form action="{{ route('admin.guest-lists.destroy', $guest) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No guest invitations found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $guests->links() }}</div>
</div>
@endsection
