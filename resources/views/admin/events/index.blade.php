@extends('admin.master')
@section('title', 'Events')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Events" subtitle="Manage events, status, and quick actions." :crumbs="['Dashboard' => route('admin.dashboard'), 'Events']">
    <a class="a-btn a-btn--primary" href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i> Create Event</a>
</x-admin.page-header>

<x-admin.flash />

<nav class="a-tabs" aria-label="Event filter">
    <a href="{{ route('admin.events.index') }}" class="{{ $archived === '' ? 'is-active' : '' }}" @if($archived === '') aria-current="page" @endif>Current</a>
    <a href="{{ route('admin.events.index', ['archived' => 'only']) }}" class="{{ $archived === 'only' ? 'is-active' : '' }}" @if($archived === 'only') aria-current="page" @endif>Archived @if($archivedCount)<span class="a-table__sub" style="display:inline">({{ $archivedCount }})</span>@endif</a>
    <a href="{{ route('admin.events.index', ['archived' => 'all']) }}" class="{{ $archived === 'all' ? 'is-active' : '' }}" @if($archived === 'all') aria-current="page" @endif>All</a>
</nav>

<x-admin.card flush :title="$archived === 'only' ? 'Archived Events' : ($archived === 'all' ? 'All Events (including archived)' : 'Events')" :subtitle="number_format($events->total()).' total'">
    @if($events->isEmpty())
        @if($archived === 'only')
            <x-admin.empty icon="fa-box-archive" title="No archived events" text="Archived events are kept here with all their orders and tickets." />
        @else
        <x-admin.empty icon="fa-calendar-days" title="No events found" text="Create your first event to start selling tickets.">
            <a class="a-btn a-btn--primary" href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i> Create Event</a>
        </x-admin.empty>
        @endif
    @else
        <x-admin.table stack caption="Events">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Date</th><th>Location</th><th>Status</th><th class="a-num">Actions</th></tr>
            </thead>
            <tbody>
            @foreach($events as $event)
                <tr>
                    <td data-label="ID">{{ $event->id }}</td>
                    <td data-label="Name" class="a-wrap"><a class="a-table__main" href="{{ route('admin.events.edit', $event) }}">{{ $event->name }}</a></td>
                    <td data-label="Date"><div class="a-cellstack">{{ $event->event_date->format('Y-m-d') }}<span class="a-table__sub">{{ $event->event_time }}</span></div></td>
                    <td data-label="Location" class="a-wrap">{{ $event->location }}</td>
                    <td data-label="Status"><x-admin.status :status="$event->status" kind="event" /></td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('front.events.show', $event) }}" title="View on website" aria-label="View {{ $event->name }} on website" target="_blank" rel="noopener"><i class="fa fa-eye"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.dashboard', $event) }}" title="Event dashboard" aria-label="Dashboard for {{ $event->name }}"><i class="fa fa-gauge"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.report', $event) }}" title="Event report" aria-label="Report for {{ $event->name }}"><i class="fa fa-chart-line"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.edit', $event) }}" title="Edit" aria-label="Edit {{ $event->name }}"><i class="fa fa-pen"></i></a>
                            @can('events.create')
                            <form method="POST" action="{{ route('admin.events.duplicate', $event) }}" data-a-confirm="Duplicate &quot;{{ $event->name }}&quot;? A new draft copy is created with the same details, ticket types, fees, line-up and photos. Orders, tickets and payments are not copied." data-a-confirm-title="Duplicate event" data-a-confirm-ok="Duplicate">
                                @csrf
                                <button type="submit" class="a-btn a-btn--outline a-btn--sm" title="Duplicate" aria-label="Duplicate {{ $event->name }}"><i class="fa fa-copy"></i></button>
                            </form>
                            @endcan
                            @if($event->isArchived())
                                @can('events.restore')
                                <form method="POST" action="{{ route('admin.events.restore', $event) }}" data-a-confirm="Restore &quot;{{ $event->name }}&quot;? It is checked for readiness first and is only put back on sale if it is ready." data-a-confirm-title="Restore event" data-a-confirm-ok="Restore">
                                    @csrf
                                    <button type="submit" class="a-btn a-btn--outline a-btn--sm" title="Restore" aria-label="Restore {{ $event->name }}"><i class="fa fa-rotate-left"></i> Restore</button>
                                </form>
                                @endcan
                            @else
                                @can('events.archive')
                                <form method="POST" action="{{ route('admin.events.archive', $event) }}" data-a-confirm="Archive &quot;{{ $event->name }}&quot;? It goes off sale and disappears from the public site. Orders, payments, issued tickets and check-ins are kept, and tickets already sold still work." data-a-confirm-title="Archive event" data-a-confirm-ok="Archive">
                                    @csrf
                                    <button type="submit" class="a-btn a-btn--outline a-btn--sm" title="Archive" aria-label="Archive {{ $event->name }}"><i class="fa fa-box-archive"></i></button>
                                </form>
                                @endcan
                            @endif
                            @can('events.delete')
                            @if($deletable[$event->id] ?? false)
                                <form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-a-confirm="Delete &quot;{{ $event->name }}&quot; permanently? It has no sales or tickets. This cannot be undone." data-a-confirm-title="Delete event permanently" data-a-confirm-ok="Delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a-btn a-btn--danger a-btn--sm" title="Delete permanently (no sales history)" aria-label="Delete {{ $event->name }} permanently"><i class="fa fa-trash"></i></button>
                                </form>
                            @endif
                            @endcan
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
