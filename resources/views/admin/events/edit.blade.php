@extends('admin.master')
@section('title', 'Edit Event')

@section('content')
<x-admin.page-header title="Edit Event" :subtitle="$event->name" :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.dashboard', $event) }}" class="a-btn a-btn--outline"><i class="fa fa-gauge"></i> Dashboard</a>
    <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--outline">Back to Events</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ route('admin.events.update',$event) }}" enctype="multipart/form-data" data-a-loading>
    @csrf
    @method('PUT')
    <x-admin.card>
        @include('admin.events.form')

        <div class="a-form-actions">
            <a href="{{ route('admin.events.index') }}" class="a-btn a-btn--ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn--primary">Update Event</button>
        </div>
    </x-admin.card>
</form>
@endsection
