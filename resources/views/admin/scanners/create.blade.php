@extends('admin.master')

@section('content')
<div class="content py-4" style="max-width: 760px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">Add Scanner User</h2>
    <a href="{{ route('admin.scanners.index') }}" class="btn btn-alt-secondary">Back</a>
  </div>

  <div class="block block-rounded">
    <div class="block-content block-content-full">
      <form method="POST" action="{{ route('admin.scanners.store') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" value="{{ old('username') }}" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Create Scanner</button>
      </form>
    </div>
  </div>
</div>
@endsection
