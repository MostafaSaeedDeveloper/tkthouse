@extends('admin.master')
@section('title', $event->name)

@section('content')
<x-admin.page-header :title="$event->name" subtitle="Event overview" :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.edit', $event) }}" class="a-btn a-btn--primary"><i class="fa fa-pen"></i> Edit</a>
    <x-admin.dropdown label="More" icon="">
        <a class="a-menu__item" href="{{ route('admin.events.dashboard', $event) }}"><i class="fa fa-gauge"></i> Dashboard</a>
        <a class="a-menu__item" href="{{ route('admin.events.report', $event) }}"><i class="fa fa-chart-line"></i> Report</a>
        <a class="a-menu__item" href="{{ route('admin.events.index') }}"><i class="fa fa-arrow-left"></i> Back to events</a>
    </x-admin.dropdown>
</x-admin.page-header>

<x-admin.flash />

<x-admin.card title="Details">
    <dl class="a-kv">
        <div><dt>Status</dt><dd><x-admin.status :status="$event->status" kind="event" /></dd></div>
        <div><dt>Date</dt><dd>{{ $event->event_date->format('Y-m-d') }} {{ $event->event_time }}</dd></div>
        <div><dt>Location</dt><dd>{{ $event->location }}</dd></div>
    </dl>
    <div class="a-form-section">
        <h3 class="a-form-section__title">Description</h3>
        <div class="a-ev-desc">{{ $event->description }}</div>
    </div>
</x-admin.card>
@endsection
