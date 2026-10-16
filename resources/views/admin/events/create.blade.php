@extends('admin.master')
@section('title', 'Create Event')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Create Event" subtitle="Set up the event details, ticket types, fees and lineup." :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), 'Create']">
    <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--outline">Back to Events</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ route('admin.events.store') }}" enctype="multipart/form-data" data-a-loading>
    @csrf
    <x-admin.card>
        @include('admin.events.form')

        <div class="a-form-actions">
            <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn--primary">Create Event</button>
        </div>
    </x-admin.card>
</form>
@endsection
