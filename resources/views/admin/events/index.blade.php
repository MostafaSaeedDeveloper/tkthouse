@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Events</h1>
        </div>
        <div class="mt-3 mt-md-0">
            <a class="btn btn-primary" href="{{ route('admin.events.create') }}">
                <i class="fa fa-plus me-1"></i> Create Event
            </a>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">All Events</h3>
            <div class="block-options">
                <span class="badge bg-primary">{{ $events->total() }} Total</span>
            </div>
        </div>

        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th style="width: 30%">Name</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th class="text-end" style="width: 180px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.events.edit', $event) }}" class="fw-semibold text-dark">
                                        {{ $event->name }}
                                    </a>
                                </td>
                                <td>{{ $event->event_date->format('Y-m-d') }}<br><span class="fs-sm text-muted">{{ $event->event_time }}</span></td>
                                <td>{{ $event->location }}</td>
                                <td>
                                    <span class="badge bg-primary">{{ str($event->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('admin.events.show', $event) }}" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.events.edit', $event) }}" title="Edit">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-alt-danger" title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No events found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $events->links() }}
    </div>
</div>
@endsection
