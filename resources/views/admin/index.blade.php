@extends('admin.master')
@section('content')
<div class="content">
    <div class="row g-3">
        <div class="col-md-4"><a href="{{ route('admin.users.index') }}" class="block block-rounded block-link-shadow text-center"><div class="block-content"><p class="fs-3 fw-bold mb-1">Users</p><p class="text-muted mb-0">CRUD + roles + login tracking</p></div></a></div>
        <div class="col-md-4"><a href="{{ route('admin.roles.index') }}" class="block block-rounded block-link-shadow text-center"><div class="block-content"><p class="fs-3 fw-bold mb-1">Roles & Permissions</p><p class="text-muted mb-0">Manage access rules</p></div></a></div>
        <div class="col-md-4"><a href="{{ route('admin.events.index') }}" class="block block-rounded block-link-shadow text-center"><div class="block-content"><p class="fs-3 fw-bold mb-1">Events</p><p class="text-muted mb-0">Events + tickets + fees</p></div></a></div>
    </div>
</div>
@endsection
