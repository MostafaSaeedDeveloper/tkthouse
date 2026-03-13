@extends('admin.master')

@section('content')
<div class="content py-4" style="max-width: 760px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">Add Scanner User</h2>
    <a href="{{ route('admin.scanners.index') }}" class="btn btn-alt-secondary">Back</a>
  </div>

  <div class="block block-rounded">
    <div class="block-content block-content-full">
      @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="{{ route('admin.scanners.store') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
        </div>
        @error('name')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required>
        </div>
        @error('username')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>
        </div>
        @error('email')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
        </div>
        @error('password')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror

        <button type="submit" class="btn btn-primary">Create Scanner</button>
      </form>
    </div>
  </div>
</div>
@endsection
