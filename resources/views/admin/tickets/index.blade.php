@extends('admin.master')
@section('title', 'Tickets')

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
    <x-admin.search name="search" :value="request('search')" placeholder="Ticket # / holder" />
    <x-admin.select label="Event" name="event_name" id="tkEvent" class="no-select2" placeholder="All events" :options="collect($eventNames)->mapWithKeys(fn ($n) => [$n => $n])->all()" :value="request('event_name')" />
    <x-admin.select label="Status" name="status" id="tkStatus" class="no-select2" placeholder="All" :options="$statusOptions" :value="request('status')" />
</x-admin.filters>

<x-admin.card flush title="All Tickets" :subtitle="number_format($tickets->total()).' total'">
    @if($tickets->isEmpty())
        <x-admin.empty icon="fa-ticket" title="No tickets found" text="Try a different search or filter. Tickets appear here once orders are paid or invitations are created." />
    @else
        <x-admin.table stack caption="Tickets">
            <thead>
                <tr><th>Ticket</th><th>Holder</th><th>Event</th><th>Status</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td data-label="Ticket"><a class="a-table__main" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number ?? '-' }}</a>
                        <span class="a-table__sub">Order {{ $ticket->order?->order_number ?? '-' }}</span></td>
                    <td data-label="Holder"><span class="a-table__main a-trunc a-tk-cell" title="{{ $ticket->holder_name }}">{{ $ticket->holder_name ?: '-' }}</span>
                        @if($ticket->holder_email)<span class="a-table__sub a-trunc a-tk-cell" title="{{ $ticket->holder_email }}">{{ $ticket->holder_email }}</span>@endif</td>
                    <td data-label="Event"><span class="a-table__main a-trunc a-tk-cell" title="{{ $ticket->eventLabel() }}">{{ $ticket->eventLabel() ?: '-' }}</span>
                        @if($ticket->ticketTypeLabel() && $ticket->ticketTypeLabel() !== $ticket->eventLabel())<span class="a-table__sub a-trunc a-tk-cell" title="{{ $ticket->ticketTypeLabel() }}">{{ $ticket->ticketTypeLabel() }}</span>@endif</td>
                    <td data-label="Status"><x-admin.status :status="$ticket->status" kind="ticket" /></td>
                    <td data-label="">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.tickets.show', $ticket) }}" aria-label="View ticket {{ $ticket->ticket_number }}">View</a>
                            @canany(['tickets.update', 'tickets.delete'])
                            <x-admin.dropdown label="" aria-label="More actions">
                                @can('tickets.update')
                                    <a class="a-menu__item" href="{{ route('admin.tickets.edit', $ticket) }}"><i class="fa fa-pen"></i> Edit</a>
                                @endcan
                                @can('tickets.delete')
                                    <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" data-a-confirm="Delete ticket {{ $ticket->ticket_number }}? This cannot be undone." data-a-confirm-title="Delete ticket" data-a-confirm-ok="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button>
                                    </form>
                                @endcan
                            </x-admin.dropdown>
                            @endcanany
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
