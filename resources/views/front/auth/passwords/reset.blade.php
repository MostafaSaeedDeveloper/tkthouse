@extends('front.layout.master')

@section('content')
<style>
.auth-page{background:#060608;min-height:100vh;padding:64px 0 80px;display:flex;align-items:center;color:#e8e8ef;font-family:'DM Sans',sans-serif;}
.auth-wrap{max-width:460px;margin:0 auto;width:100%;padding:0 16px;}
.auth-card{background:#0e0e12;border:1px solid rgba(255,255,255,.07);border-radius:14px;overflow:hidden;position:relative;}
.auth-card::before{content:'';position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,transparent,#f5b800 40%,#c99300 70%,transparent);} 
.auth-card-body{padding:32px 30px 28px;}
.auth-heading h2{font-size:20px;font-weight:800;margin:0 0 5px;color:#fff;}
.auth-heading p{font-size:13px;color:#6b6b7e;margin:0 0 22px;}
.auth-field{margin-bottom:14px;}
.auth-field label{display:block;font-size:10px;color:#6b6b7e;letter-spacing:1px;text-transform:uppercase;margin-bottom:7px;}
.auth-field input{width:100%;background:#16161d;border:1px solid rgba(255,255,255,.07);border-radius:8px;color:#e8e8ef;padding:11px 14px;}
.auth-submit{width:100%;margin-top:6px;background:#f5b800;border:none;border-radius:8px;padding:13px 24px;font-weight:800;text-transform:uppercase;cursor:pointer;}
.auth-alert{border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:13px;background:rgba(232,68,90,.08);border:1px solid rgba(232,68,90,.28);color:#f0849a;}
</style>

<div class="sub-banner"><div class="container"><h6>Reset Password</h6></div></div>

<section class="auth-page">
    <div class="container">
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="auth-card-body">
                    <div class="auth-heading">
                        <h2>Create a new password</h2>
                        <p>Use a strong password for your account.</p>
                    </div>

                    @if($errors->any())
                        <div class="auth-alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="auth-field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus>
                        </div>

                        <div class="auth-field">
                            <label for="password">New Password</label>
                            <input id="password" type="password" name="password" required>
                        </div>

                        <div class="auth-field">
                            <label for="password-confirm">Confirm Password</label>
                            <input id="password-confirm" type="password" name="password_confirmation" required>
                        </div>

                        <button type="submit" class="auth-submit">Reset password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
