@extends('front.layout.app')

@section('title', 'Forgot password')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Reset your password</h1>
                <p>Enter your account email and we'll send you a reset link.</p>
            </div>
            @if($errors->any())
                <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="tkt-field">
                    <label class="tkt-label" for="email">Email address</label>
                    <input class="tkt-input {{ $errors->has('email') ? 'is-invalid' : '' }}" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required autofocus>
                </div>
                <button type="submit" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block">Send reset link</button>
            </form>
        </div>
        <div class="tkt-auth-card__foot">Remembered it? <a href="{{ route('front.customer.login') }}">Back to login</a></div>
    </div>
</section>
@endsection
