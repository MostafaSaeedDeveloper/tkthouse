@extends('admin.master')
@section('content')
<div class="content">
    @include('admin.partials.flash')
    <h1 class="h3 mb-3">Create Ticket</h1>
    <form method="POST" action="{{ route('admin.tickets.store') }}" class="block block-rounded">@csrf
        <div class="block-content">@include('admin.tickets.form')</div>
        <div class="block-content block-content-full bg-body-light d-flex justify-content-end gap-2">
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-alt-secondary">Cancel</a>
            <button class="btn btn-primary">Create Ticket</button>
        </div>
    </form>
</div>
@endsection
