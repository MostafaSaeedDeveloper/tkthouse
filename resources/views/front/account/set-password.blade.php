@extends('front.layout.app')

@section('title', 'Set a password')

@section('content')
<section class="tkt-container tkt-auth-page">
    <div class="tkt-auth-card">
        <div class="tkt-auth-card__body">
            <div class="tkt-auth__heading">
                <h1 class="tkt-h2">Set a password</h1>
                <p>Optional. You can always sign in with an email code instead.</p>
            </div>
            @if($errors->any())
                <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('front.account.set-password.store') }}" data-tkt-submit>
                @csrf
                <div class="tkt-field">
                    <label class="tkt-label" for="password">New password</label>
                    <input class="tkt-input" id="password" type="password" name="password" autocomplete="new-password" required autofocus>
                    <div class="tkt-field__hint">At least 8 characters.</div>
                </div>
                <div class="tkt-field">
                    <label class="tkt-label" for="password_confirmation">Confirm password</label>
                    <input class="tkt-input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
                <button class="tkt-btn tkt-btn--primary tkt-btn--lg tkt-btn--block" type="submit">Save password</button>
            </form>
        </div>
        <div class="tkt-auth-card__foot"><a href="{{ route('front.account.tickets') }}">Skip for now</a></div>
    </div>
</section>
@endsection
