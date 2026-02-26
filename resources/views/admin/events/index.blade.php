@extends('admin.master')
@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between mb-3">
        <h2 class="h4">Events</h2>
        <a class="btn btn-primary" href="{{ route('admin.events.create') }}">Create Event</a>
    </div>

    <div class="block block-rounded">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                        <tr>
                            <td>
                                <a href="{{ route('admin.events.show', $event) }}" class="fw-semibold">
                                    {{ $event->name }}
                                </a>
                            </td>
                            <td>{{ $event->event_date->format('Y-m-d') }} {{ $event->event_time }}</td>
                            <td>{{ $event->location }}</td>
                            <td><span class="badge bg-primary">{{ str($event->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-alt-info" href="{{ route('admin.events.show',$event) }}" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.events.edit',$event) }}" title="Edit">
                                    <i class="fa fa-pen"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.events.destroy',$event) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-alt-danger" title="Delete"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $events->links() }}
</div>
@endsection
