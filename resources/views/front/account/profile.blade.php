@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>My Dashboard</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @include('front.account.partials.tabs')

        <div class="card bg-dark border-secondary mx-auto" style="max-width:720px;">
            <div class="card-header border-secondary text-warning">Edit Profile</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    @if($user->profileImageUrl())
                        <img src="{{ $user->profileImageUrl() }}" alt="Profile image" class="rounded-circle" style="width:110px;height:110px;object-fit:cover;">
                    @else
                        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center bg-secondary text-white" style="width:110px;height:110px;font-size:35px;">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('front.account.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" required>
                        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="profile_image" class="form-control @error('profile_image') is-invalid @enderror" accept="image/*">
                        @error('profile_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button class="btn btn-warning" type="submit">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
