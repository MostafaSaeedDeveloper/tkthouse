@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>Customer Login</h6></div></div>
<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h4 class="text-warning mb-4">Login to Continue</h4>
                        <form action="{{ route('front.customer.login.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Email or Username</label>
                                <input type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" required autofocus>
                                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-warning w-100" type="submit">Login</button>
                        </form>
                        <p class="mt-3 mb-0">New customer? <a href="{{ route('front.customer.register') }}">Create account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
