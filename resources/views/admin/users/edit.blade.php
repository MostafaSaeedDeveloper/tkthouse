@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Edit User</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.users.update',$user) }}" class="block block-rounded block-content">@csrf @method('PUT') @include('admin.users.form')<button class="btn btn-primary">Update</button></form></div>
@endsection
