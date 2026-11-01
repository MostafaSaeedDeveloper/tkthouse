@extends('admin.master')
@section('title', 'Deleted Tickets')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Deleted Tickets" subtitle="Deleted tickets stay here and can be restored." :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), 'Deleted']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.tickets.index') }}"><i class="fa fa-arrow-left"></i> Back to Tickets</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.tickets.trash')" :reset="route('admin.tickets.trash')">
    <div class="a-field"><label class="form-label" for="tkSearch">Search</label><input type="text" id="tkSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Ticket # / holder"></div>
</x-admin.filters>

<x-admin.card flush title="Trash" :subtitle="number_format($tickets->total()).' deleted'">
    @if($tickets->isEmpty())
        <x-admin.empty icon="fa-trash-can" title="No deleted tickets found" text="Deleted tickets will appear here and can be restored." />
    @else
        <x-admin.table stack caption="Deleted tickets">
            <thead>
                <tr><th>Ticket Number</th><th>Holder</th><th>Event</th><th>Order</th><th>Status</th><th>Deleted</th><th class="a-num">Actions</th></tr>
            </thead>
            <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td data-label="Ticket Number"><span class="a-table__main">{{ $ticket->ticket_number ?? '-' }}</span>@if($ticket->isGuestList())<span class="a-table__sub">{{ $ticket->guestTypeLabel() }}</span>@endif</td>
                    <td data-label="Holder" class="a-wrap">{{ $ticket->holder_name ?: '-' }}</td>
                    <td data-label="Event" class="a-wrap">{{ $ticket->eventLabel() ?: '-' }}</td>
                    <td data-label="Order">{{ $ticket->order?->order_number ?? '-' }}</td>
                    <td data-label="Status"><x-admin.status :status="$ticket->status" kind="ticket" /></td>
                    <td data-label="Deleted"><span class="a-table__main">{{ optional($ticket->deleted_at)->format('d M Y') }}</span><span class="a-table__sub">{{ optional($ticket->deleted_at)->format('h:i A') }}</span></td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                            <form method="POST" action="{{ route('admin.tickets.restore', $ticket->id) }}" data-a-confirm="Restore ticket {{ $ticket->ticket_number }}?" data-a-confirm-title="Restore ticket" data-a-confirm-ok="Restore">
                                @csrf
                                <button class="a-btn a-btn--success a-btn--sm" type="submit" aria-label="Restore ticket {{ $ticket->ticket_number }}"><i class="fa fa-rotate-left"></i> Restore</button>
                            </form>
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
