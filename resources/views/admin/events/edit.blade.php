@extends('admin.master')
@section('title', 'Edit Event')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Edit Event" :subtitle="$event->name" :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.dashboard', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-gauge"></i> Dashboard</a>
    <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--outline">Back to Events</a>
</x-admin.page-header>

<x-admin.flash />
@if($event->isArchived())
    <x-admin.alert type="info"><strong>This event is archived.</strong> It is off sale and hidden from the public site; its status only changes through <em>Restore</em>. Sold tickets keep working.@can('events.restore') <form method="POST" action="{{ route('admin.events.restore', $event) }}" style="display:inline">@csrf<button class="a-btn a-btn--outline a-btn--sm" type="submit">Restore event</button></form>@endcan</x-admin.alert>
@endif

<form method="POST" action="{{ route('admin.events.update',$event) }}" enctype="multipart/form-data" data-a-loading>
    @csrf
    @method('PUT')
    <x-admin.card>
        @include('admin.events.form')

        <div class="a-form-actions a-ev-actionbar">
            <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn--primary">Update Event</button>
        </div>
    </x-admin.card>
</form>
@endsection
