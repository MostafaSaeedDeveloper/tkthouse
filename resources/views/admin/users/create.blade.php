@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Create User</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.users.store') }}" class="block block-rounded block-content">@csrf @include('admin.users.form')<button class="btn btn-primary">Save</button></form></div>
@endsection
