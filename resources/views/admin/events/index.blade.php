@extends('admin.master')
@section('title', 'Events')

@section('content')
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
                <tr><th>Name</th><th>Date</th><th>Location</th><th>Status</th><th class="a-num">Actions</th></tr>
            </thead>
            <tbody>
            @foreach($events as $event)
                <tr>
                    <td data-label="Name" class="a-wrap"><a class="a-table__main" href="{{ route('admin.events.edit', $event) }}">{{ $event->name }}</a></td>
                    <td data-label="Date">{{ $event->event_date->format('Y-m-d') }}<span class="a-table__sub">{{ $event->event_time }}</span></td>
                    <td data-label="Location" class="a-wrap">{{ $event->location }}</td>
                    <td data-label="Status"><x-admin.status :status="$event->status" kind="event" /></td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('front.events.show', $event) }}" title="View on website" aria-label="View {{ $event->name }} on website" target="_blank" rel="noopener"><i class="fa fa-eye"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.dashboard', $event) }}" title="Event dashboard" aria-label="Dashboard for {{ $event->name }}"><i class="fa fa-gauge"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.report', $event) }}" title="Event report" aria-label="Report for {{ $event->name }}"><i class="fa fa-chart-line"></i></a>
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.events.edit', $event) }}" title="Edit" aria-label="Edit {{ $event->name }}"><i class="fa fa-pen"></i></a>
                            <form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-a-confirm="Delete &quot;{{ $event->name }}&quot;? This cannot be undone." data-a-confirm-title="Delete event" data-a-confirm-ok="Delete">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="a-btn a-btn--danger a-btn--sm" title="Delete" aria-label="Delete {{ $event->name }}"><i class="fa fa-trash"></i></button>
                            </form>
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
