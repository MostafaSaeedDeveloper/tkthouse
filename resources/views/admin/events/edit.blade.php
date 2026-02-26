@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Edit Event</h2>
        <a href="{{ route('admin.events.index') }}" class="btn btn-alt-secondary btn-sm">Back to Events</a>
    </div>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.events.update',$event) }}" enctype="multipart/form-data" class="block block-rounded">
        @csrf
        @method('PUT')

        <div class="block-content">
            @include('admin.events.form')
        </div>

        <div class="block-content block-content-full bg-body-light d-flex justify-content-end gap-2">
            <a href="{{ route('admin.events.index') }}" class="btn btn-alt-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Event</button>
        </div>
    </form>
</div>
@endsection
