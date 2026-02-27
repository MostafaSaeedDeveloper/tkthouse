@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>Create Customer Account</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h4 class="text-warning mb-4">Create a New Account</h4>
                        <form method="POST" action="{{ route('front.customer.register.store') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirmation" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">Create Account</button>
                        </form>
                        <p class="mt-3 mb-0">Already have an account? <a href="{{ route('front.customer.login', ['redirect' => request('redirect')]) }}">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
