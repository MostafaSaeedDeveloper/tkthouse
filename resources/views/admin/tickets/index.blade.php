@extends('admin.master')
@section('title', 'Tickets')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
@php($statusOptions = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
<x-admin.page-header title="Tickets" subtitle="Every issued ticket with its holder, event and check-in status." :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets']">
    @can('scanner.access')
        <a href="{{ route('admin.tickets.scanner') }}" class="a-btn a-btn--outline"><i class="fa fa-qrcode"></i> QR Scanner</a>
    @endcan
    @can('tickets.create')
        <a href="{{ route('admin.tickets.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Create Ticket</a>
    @endcan
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.tickets.index')" :reset="route('admin.tickets.index')">
    <div class="a-field"><label class="form-label" for="tkSearch">Search</label><input type="text" id="tkSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Ticket # / holder"></div>
    <div class="a-field"><label class="form-label" for="tkEvent">Event Name</label>
        <select id="tkEvent" name="event_name" class="form-select">
            <option value="">All events</option>
            @foreach($eventNames as $eventName)
                <option value="{{ $eventName }}" @selected(request('event_name') === $eventName)>{{ $eventName }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="tkStatus">Status</label>
        <select id="tkStatus" name="status" class="form-select">
            <option value="">All</option>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
</x-admin.filters>

<x-admin.card flush>
    @if($tickets->isEmpty())
        <x-admin.empty icon="fa-ticket" title="No tickets found" text="Try a different search or filter. Tickets appear here once orders are paid or invitations are created." />
    @else
        <x-admin.table stack caption="Tickets">
            <thead>
                <tr><th>Ticket Number</th><th>Holder</th><th>Event</th><th>Order</th><th>Status</th><th class="a-num">Actions</th></tr>
            </thead>
            <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td data-label="Ticket Number"><a class="a-table__main" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number ?? '-' }}</a></td>
                    <td data-label="Holder" class="a-wrap">{{ $ticket->holder_name ?: '-' }}</td>
                    <td data-label="Event" class="a-wrap">{{ $ticket->eventLabel() ?: '-' }}</td>
                    <td data-label="Order">{{ $ticket->order?->order_number ?? '-' }}</td>
                    <td data-label="Status"><x-admin.status :status="$ticket->status" kind="ticket" /></td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.tickets.show', $ticket) }}" aria-label="View ticket {{ $ticket->ticket_number }}" title="View"><i class="fa fa-eye"></i></a>
                            @can('tickets.update')
                                <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.tickets.edit', $ticket) }}" aria-label="Edit ticket {{ $ticket->ticket_number }}" title="Edit"><i class="fa fa-pen"></i></a>
                            @endcan
                            @can('tickets.delete')
                                <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" data-a-confirm="Delete ticket {{ $ticket->ticket_number }}? This cannot be undone." data-a-confirm-title="Delete ticket" data-a-confirm-ok="Delete">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a-btn a-btn--danger a-btn--sm" type="submit" aria-label="Delete ticket {{ $ticket->ticket_number }}" title="Delete"><i class="fa fa-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$tickets" />
    @endif
</x-admin.card>
@endsection
