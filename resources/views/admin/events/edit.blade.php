@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Edit Event</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.events.update',$event) }}" enctype="multipart/form-data" class="block block-rounded block-content">@csrf @method('PUT') @include('admin.events.form')<button class="btn btn-primary mt-3">Update Event</button></form></div>
@endsection
