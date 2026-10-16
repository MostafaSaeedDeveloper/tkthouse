@extends('admin.master')
@section('title', 'Events')

@section('content')
@php $events->loadMissing('tickets'); @endphp
<x-admin.page-header title="Events" subtitle="Manage events, status, and quick actions." :crumbs="['Dashboard' => route('admin.dashboard'), 'Events']">
    <a class="a-btn a-btn--primary" href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i> Create Event</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.card flush title="All Events" :subtitle="number_format($events->total()).' total'">
    @if($events->isEmpty())
        <x-admin.empty icon="fa-calendar-days" title="No events found" text="Create your first event to start selling tickets.">
            <a class="a-btn a-btn--primary" href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i> Create Event</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Events">
            <thead>
                <tr><th>Event</th><th>Date</th><th>Ticket types</th><th>Status</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
            @foreach($events as $event)
                <tr>
                    <td data-label="Event">
                        <a class="a-table__main a-trunc a-ev-cell" href="{{ route('admin.events.edit', $event) }}" title="{{ $event->name }}">{{ $event->name }}</a>
                        <span class="a-table__sub a-trunc a-ev-cell" title="{{ $event->location }}">{{ $event->location ?: '—' }}</span>
                    </td>
                    <td data-label="Date">{{ $event->event_date->format('d M Y') }}<span class="a-table__sub">{{ $event->event_time }}</span></td>
                    <td data-label="Ticket types"><x-admin.more :items="$event->tickets->pluck('name')" /></td>
                    <td data-label="Status"><x-admin.status :status="$event->status" kind="event" /></td>
                    <td data-label="">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.edit', $event) }}" aria-label="Edit {{ $event->name }}"><i class="fa fa-pen"></i> Edit</a>
                            <x-admin.dropdown label="" aria-label="More actions">
                                <a class="a-menu__item" href="{{ route('admin.events.dashboard', $event) }}"><i class="fa fa-gauge"></i> Dashboard</a>
                                <a class="a-menu__item" href="{{ route('admin.events.report', $event) }}"><i class="fa fa-chart-line"></i> Report</a>
                                <a class="a-menu__item" href="{{ route('front.events.show', $event) }}" target="_blank" rel="noopener"><i class="fa fa-up-right-from-square"></i> View on website</a>
                                <div class="a-menu__sep"></div>
                                <form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-a-confirm="Delete &quot;{{ $event->name }}&quot;? This cannot be undone." data-a-confirm-title="Delete event" data-a-confirm-ok="Delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a-menu__item a-menu__item--danger"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </x-admin.dropdown>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$events" />
    @endif
</x-admin.card>
@endsection
