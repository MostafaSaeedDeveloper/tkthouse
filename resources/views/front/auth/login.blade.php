@extends('front.layout.app')

@section('title', 'Sign in')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Welcome to TKT House</h1>
                <p>Log in to continue with your booking.</p>
            </div>
            @if($errors->any())
                <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('front.customer.login.store') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">
                <div class="tkt-field">
                    <label class="tkt-label" for="login">Email or username</label>
                    <input class="tkt-input {{ $errors->has('login') ? 'is-invalid' : '' }}" id="login" type="text" name="login" value="{{ old('login') }}" placeholder="you@example.com" autocomplete="username" required autofocus>
                    @error('login')<div class="tkt-field__error">{{ $message }}</div>@enderror
                </div>
                <div class="tkt-field">
                    <label class="tkt-label" for="password">Password</label>
                    <input class="tkt-input {{ $errors->has('password') ? 'is-invalid' : '' }}" id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    @error('password')<div class="tkt-field__error">{{ $message }}</div>@enderror
                </div>
                <div class="tkt-auth__row">
                    <label class="tkt-check"><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}><span>Remember me</span></label>
                    <a class="tkt-link" href="{{ route('password.request') }}">Forgot password?</a>
                </div>
                <p class="tkt-auth__legal">By continuing, you agree to our <a href="{{ route('front.terms') }}" target="_blank" rel="noopener">Terms and Conditions</a> and understand how your information is used as described in our <a href="{{ route('front.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</p>
                <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" type="submit">Log in</button>
            </form>
        </div>
        <div class="tkt-auth-card__foot">New here? <a href="{{ route('front.customer.register', ['redirect' => request('redirect')]) }}">Create an account</a></div>
    </div>
</section>
@endsection
