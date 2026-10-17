@extends('admin.master')
@section('title', $event->name)
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header :title="$event->name" subtitle="Event overview" :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.dashboard', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-gauge"></i> Dashboard</a>
    <a href="{{ route('admin.events.report', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-chart-line"></i> Report</a>
    <a href="{{ route('admin.events.edit', $event) }}" class="a-btn a-btn--primary"><i class="fa fa-pen"></i> Edit</a>
    @if($event->isArchived())
        @can('events.restore')<form method="POST" action="{{ route('admin.events.restore', $event) }}" data-a-confirm="Restore this event? It is checked for readiness first." data-a-confirm-title="Restore event" data-a-confirm-ok="Restore">@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-rotate-left"></i> Restore</button></form>@endcan
    @else
        @can('events.archive')<form method="POST" action="{{ route('admin.events.archive', $event) }}" data-a-confirm="Archive this event? It goes off sale and out of the public site; orders, payments and tickets are kept." data-a-confirm-title="Archive event" data-a-confirm-ok="Archive">@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-box-archive"></i> Archive</button></form>@endcan
    @endif
    <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--ghost">Back</a>
</x-admin.page-header>

<x-admin.flash />
@if($event->isArchived())
    <x-admin.alert type="info"><strong>Archived{{ $event->archived_at ? ' on '.$event->archived_at->format('d M Y') : '' }}.</strong> This event is off sale and hidden from the public site. Tickets already sold keep working (QR and check-in are unaffected).</x-admin.alert>
@endif

<x-admin.card title="Details">
    <dl class="a-kv">
        <div><dt>Status</dt><dd><x-admin.status :status="$event->status" kind="event" /></dd></div>
        <div><dt>Date</dt><dd>{{ $event->event_date->format('Y-m-d') }} {{ $event->event_time }}</dd></div>
        <div><dt>Location</dt><dd>{{ $event->location }}</dd></div>
    </dl>
    <div class="a-ev-block">
        <strong>Description</strong>
        <div class="a-ev-desc">{{ $event->description }}</div>
    </div>
</x-admin.card>

<x-admin.card title="Deletion" subtitle="Events with history can only be archived." style="margin-top:12px">
    @if($dependencies['total'] > 0)
        <p class="a-table__sub" style="margin:0 0 8px">This event cannot be deleted permanently because it has: <strong>{{ implode(', ', $dependencies['messages']) }}</strong>.</p>
        <p class="a-table__sub" style="margin:0">Use <em>Archive</em> instead: nothing is lost and it disappears from sale.</p>
    @else
        <p class="a-table__sub" style="margin:0 0 10px">This event has no sales, payments, tickets or check-ins, so it can be deleted permanently.</p>
        @can('events.delete')
        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-a-confirm="Delete &quot;{{ $event->name }}&quot; permanently? This cannot be undone." data-a-confirm-title="Delete event permanently" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger" type="submit"><i class="fa fa-trash"></i> Delete permanently</button></form>
        @endcan
    @endif
</x-admin.card>
@endsection
