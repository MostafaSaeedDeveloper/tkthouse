@extends('admin.master')

@section('content')
<div class="content">
    <div class="block block-rounded">
        <div class="block-header block-header-default"><h3 class="block-title">Edit Ticket</h3></div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">
                @method('PUT')
                @include('admin.tickets.form')
            </form>
        </div>
    </div>
</div>
@endsection
