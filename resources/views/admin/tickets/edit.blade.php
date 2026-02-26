@extends('admin.master')
@section('content')
<div class="content">
    @include('admin.partials.flash')
    <h1 class="h3 mb-3">Edit Ticket</h1>
    <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="block block-rounded">@csrf @method('PUT')
        <div class="block-content">@include('admin.tickets.form')</div>
        <div class="block-content block-content-full bg-body-light d-flex justify-content-end gap-2">
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-alt-secondary">Cancel</a>
            <button class="btn btn-primary">Update Ticket</button>
        </div>
    </form>
</div>
@endsection
