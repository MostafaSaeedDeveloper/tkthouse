@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="block block-rounded">
        <div class="block-header block-header-default"><h3 class="block-title">Edit Guest Invitation</h3></div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.guest-list.update', $ticket) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event</label>
                        <select name="event_id" class="form-select js-select2" required>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" @selected(old('event_id', $ticket->event_id) == $event->id)>{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $ticket->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="holder_name" class="form-control" value="{{ old('holder_name', $ticket->holder_name) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="holder_email" class="form-control" value="{{ old('holder_email', $ticket->holder_email) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $ticket->description) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a href="{{ route('admin.guest-list.show', $ticket) }}" class="btn btn-alt-secondary">View</a>
                    <a href="{{ route('admin.guest-list.index') }}" class="btn btn-alt-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
