@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Edit Permission</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.permissions.update',$permission) }}" class="block block-rounded block-content">@csrf @method('PUT') <div class="mb-3"><label class="form-label">Permission Name</label><input name="name" class="form-control" value="{{ old('name',$permission->name) }}" required></div><button class="btn btn-primary">Update</button></form></div>
@endsection
