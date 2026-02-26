@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Edit Role</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.roles.update',$role) }}" class="block block-rounded block-content">@csrf @method('PUT') @include('admin.roles.form')<button class="btn btn-primary">Update</button></form></div>
@endsection
