@extends('front.layout.master')

@section('content')
<div class="sub-banner"><div class="container"><h6>Email Verification</h6></div></div>
<section class="auth-page" style="background:#060608;min-height:100vh;padding:64px 0;">
    <div class="container" style="max-width:520px;">
        <div style="background:#0e0e12;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:28px;color:#e8e8ef;">
            <h2 style="margin:0 0 8px;font-weight:800;">Verify your email</h2>
            <p style="margin:0 0 16px;color:#9b9bab;">Enter the 6-digit OTP sent to your email address.</p>

            @if(session('success'))
                <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:rgba(10,120,40,.2);border:1px solid rgba(10,180,60,.3);">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:rgba(220,50,80,.15);border:1px solid rgba(232,68,90,.3);">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('front.customer.verify-otp.store') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">
                <label style="font-size:12px;color:#9b9bab;display:block;margin-bottom:6px;">OTP Code</label>
                <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}" value="{{ old('otp') }}" required
                       style="width:100%;background:#16161d;border:1px solid rgba(255,255,255,.08);border-radius:8px;color:#fff;padding:11px 12px;letter-spacing:4px;font-size:22px;text-align:center;margin-bottom:14px;">

                <button type="submit" style="width:100%;background:#f5b800;color:#000;border:none;border-radius:8px;padding:12px;font-weight:700;cursor:pointer;">Verify & Continue</button>
            </form>

            <form method="POST" action="{{ route('front.customer.verify-otp.resend') }}" style="margin-top:10px;text-align:center;">
                @csrf
                <button type="submit" style="background:none;border:none;color:#f5b800;cursor:pointer;">Resend OTP</button>
            </form>
        </div>
    </div>
</section>
@endsection
