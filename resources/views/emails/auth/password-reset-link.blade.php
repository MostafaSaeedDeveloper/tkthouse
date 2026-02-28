@extends('emails.layouts.base', [
  'title' => 'Reset Your Password — TKT House',
  'heroIcon' => '🔐',
  'heroTitle' => 'Password Reset Request',
  'heroText' => 'We received a request to reset your account password.<br>Click the button below to create a new password securely.',
  'footerText' => 'This message was sent because a password reset was requested for your TKT House account.',
])

@section('content')
  <p class="ep">Hi <strong>{{ $user->name ?: 'there' }}</strong>,</p>

  <p class="ep">
    You recently asked to reset the password for your TKT House account.
    For your security, this reset link will expire in
    <strong>{{ $expireMinutes }} minutes</strong>.
  </p>

  <div class="ecta-wrap">
    <a href="{{ $resetUrl }}" class="ecta">Reset Password</a>
    <p class="ecta-sub">Secure link · valid for {{ $expireMinutes }} minutes</p>
  </div>

  <p class="ep ep-sm">If the button doesn’t work, copy and paste this link into your browser:</p>
  <div class="eurl"><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></div>

  <div class="ealert gold" style="margin-top:16px;">
    If you did not request a password reset, you can safely ignore this email.
  </div>
@endsection
