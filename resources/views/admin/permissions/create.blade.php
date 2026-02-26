@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4">Create Permission</h2>@include('admin.partials.flash')<form method="POST" action="{{ route('admin.permissions.store') }}" class="block block-rounded block-content">@csrf <div class="mb-3"><label class="form-label">Permission Name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div><button class="btn btn-primary">Save</button></form></div>
@endsection
