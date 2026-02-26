@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Create Event</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.events.store') }}" enctype="multipart/form-data" class="block block-rounded block-content">@csrf @include('admin.events.form')<button class="btn btn-primary mt-3">Save Event</button></form></div>
@endsection
