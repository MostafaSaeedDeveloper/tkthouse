@extends('admin.master')
@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">{{ $event->name }}</h2>
        <div>
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-sm btn-alt-primary"><i class="fa fa-pen me-1"></i>Edit</a>
            <a href="{{ route('admin.events.index') }}" class="btn btn-sm btn-alt-secondary">Back</a>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-content">
            <p><strong>Status:</strong> {{ str($event->status)->replace('_', ' ')->title() }}</p>
            <p><strong>Date:</strong> {{ $event->event_date->format('Y-m-d') }} {{ $event->event_time }}</p>
            <p><strong>Location:</strong> {{ $event->location }}</p>
            <p><strong>Description:</strong><br>{{ $event->description }}</p>
        </div>
    </div>
</div>
@endsection
