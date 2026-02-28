@extends('front.layout.master')

@section('content')

@include('front.account.partials._acc_styles')

<div class="acc-banner">
    <div class="container">
        <div class="acc-banner-inner">
            <div>
                <div class="acc-banner-label">Account</div>
                <h1 class="acc-banner-title">Edit <span>Profile</span></h1>
                <p class="acc-banner-sub">Manage your personal information</p>
            </div>
        </div>
    </div>
</div>

<section class="acc-page">
    <div class="container">

        @if(session('success'))
            <div class="acc-alert-success">
                <i class="fa fa-circle-check"></i>
                {{ session('success') }}
            </div>
        @endif

        @include('front.account.partials.navigation')

        <div class="acc-grid-13">

            {{-- LEFT: Avatar card ── --}}
            <div class="acc-card" style="margin-bottom:0;">
                <div class="acc-card-head">
                    <div class="acc-card-title">Avatar</div>
                </div>
                <div style="padding:28px 22px; text-align:center;">
                    @if($user->profileImageUrl())
                        <img src="{{ $user->profileImageUrl() }}" class="acc-avatar" style="width:100px;height:100px;" alt="Profile">
                    @else
                        <div class="acc-avatar-initials" style="width:100px;height:100px;font-size:34px;margin-bottom:10px;">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="acc-avatar-name">{{ $user->name }}</div>
                    <div class="acc-avatar-username">{{ $user->username }}</div>
                    <div style="margin-top:10px;font-size:12px;color:var(--muted);">{{ $user->email }}</div>

                    {{-- Quick links --}}
                    <div style="margin-top:22px;display:flex;flex-direction:column;gap:8px;">
                        <a href="{{ route('front.account.orders') }}" class="acc-btn" style="justify-content:center;">
                            <i class="fa fa-bag-shopping" style="font-size:10px;"></i> My Orders
                        </a>
                        <a href="{{ route('front.account.tickets') }}" class="acc-btn" style="justify-content:center;">
                            <i class="fa fa-ticket" style="font-size:10px;"></i> My Tickets
                        </a>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Form ── --}}
            <div class="acc-card" style="margin-bottom:0;">
                <div class="acc-card-head">
                    <div class="acc-card-title">Personal Information</div>
                </div>
                <div style="padding:28px;">
                    <form method="POST" action="{{ route('front.account.profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="acc-grid-2">
                            <div class="acc-field">
                                <label>Full Name</label>
                                <input type="text" name="name" placeholder="John Doe"
                                    value="{{ old('name', $user->name) }}"
                                    class="{{ $errors->has('name') ? 'is-invalid' : '' }}" required>
                                @error('name')<div class="acc-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="acc-field">
                                <label>Username</label>
                                <input type="text" name="username" placeholder="johndoe"
                                    value="{{ old('username', $user->username) }}"
                                    class="{{ $errors->has('username') ? 'is-invalid' : '' }}" required>
                                @error('username')<div class="acc-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="acc-field">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="you@example.com"
                                value="{{ old('email', $user->email) }}"
                                class="{{ $errors->has('email') ? 'is-invalid' : '' }}" required>
                            @error('email')<div class="acc-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="acc-field">
                            <label>Profile Image</label>
                            <label class="acc-file-label">
                                <i class="fa fa-image"></i>
                                <span>Click to upload a new photo</span>
                                <input type="file" name="profile_image" accept="image/*">
                            </label>
                            <div class="acc-hint">Accepted: JPG, PNG, WEBP — max 2MB</div>
                            @error('profile_image')<div class="acc-error">{{ $message }}</div>@enderror
                        </div>

                        <div style="padding-top:6px;">
                            <button class="acc-submit" type="submit">
                                <i class="fa fa-check" style="font-size:12px;"></i>
                                Save Changes
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>

    </div>
</section>

@endsection
