@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>My Dashboard</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @include('front.account.partials.navigation')

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-2">Current Avatar</p>
                        @if($user->profileImageUrl())
                            <img src="{{ $user->profileImageUrl() }}" alt="Profile image" class="rounded-circle border border-warning p-1" style="width:140px;height:140px;object-fit:cover;">
                        @else
                            <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center bg-secondary text-white border border-warning" style="width:140px;height:140px;font-size:48px;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        @endif

                        <h5 class="mt-3 mb-1">{{ $user->name }}</h5>
                        <p class="text-muted mb-3">@ {{ $user->username }}</p>
                        <div class="small text-muted">{{ $user->email }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-header border-secondary text-warning fw-semibold">Edit Profile</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('front.account.profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" required>
                                    @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Change Profile Image</label>
                                    <input type="file" name="profile_image" class="form-control @error('profile_image') is-invalid @enderror" accept="image/*">
                                    <small class="text-muted d-block mt-2">Accepted: JPG, PNG, WEBP (max 2MB).</small>
                                    @error('profile_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button class="btn btn-warning" type="submit">
                                    <i class="si si-check me-1"></i>Save Changes
                                </button>
                                <a href="{{ route('front.account.orders') }}" class="btn btn-outline-light">View Orders</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
