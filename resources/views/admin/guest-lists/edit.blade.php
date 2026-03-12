@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Edit Guest Invitation</h3>
        </div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.guest-lists.update', $guest) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event</label>
                        <select name="event_id" class="form-select" required>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" @selected((int) old('event_id', $guest->event_id) === $event->id)>{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(['not_checked_in' => 'Not Scanned', 'checked_in' => 'Scanned', 'canceled' => 'Canceled'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', $guest->status) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="{{ old('name', $guest->holder_name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input name="email" type="email" class="form-control" value="{{ old('email', $guest->holder_email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" value="{{ old('phone', $guest->holder_phone) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type/Category</label>
                        <input name="type" class="form-control" value="{{ old('type', $guest->guest_category) }}">
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.guest-lists.index') }}">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
