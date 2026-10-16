@extends('admin.master')
@section('title', $event->name)
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header :title="$event->name" subtitle="Event overview" :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.dashboard', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-gauge"></i> Dashboard</a>
    <a href="{{ route('admin.events.report', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-chart-line"></i> Report</a>
    <a href="{{ route('admin.events.edit', $event) }}" class="a-btn a-btn--primary"><i class="fa fa-pen"></i> Edit</a>
    <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--ghost">Back</a>
</x-admin.page-header>

<x-admin.flash />

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
@endsection
