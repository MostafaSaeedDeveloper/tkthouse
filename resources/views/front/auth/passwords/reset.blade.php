@extends('front.layout.app')

@section('title', 'Create a new password')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Create a new password</h1>
                <p>Use a strong password for your account.</p>
            </div>

            @if($errors->any())
                <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="tkt-field">
                    <label class="tkt-label" for="email">Email address</label>
                    <input class="tkt-input" id="email" type="email" name="email" value="{{ $email ?? old('email') }}" autocomplete="email" required autofocus>
                </div>
                <div class="tkt-field">
                    <label class="tkt-label" for="password">New password</label>
                    <input class="tkt-input" id="password" type="password" name="password" autocomplete="new-password" required>
                </div>
                <div class="tkt-field">
                    <label class="tkt-label" for="password-confirm">Confirm password</label>
                    <input class="tkt-input" id="password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
                <button type="submit" class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block">Reset password</button>
            </form>
        </div>
    </div>
</section>
@endsection
