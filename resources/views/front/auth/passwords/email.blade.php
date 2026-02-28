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
.auth-field label{display:block;font-size:10px;color:#6b6b7e;letter-spacing:1px;text-transform:uppercase;margin-bottom:7px;}
.auth-field input{width:100%;background:#16161d;border:1px solid rgba(255,255,255,.07);border-radius:8px;color:#e8e8ef;padding:11px 14px;}
.auth-submit{width:100%;margin-top:16px;background:#f5b800;border:none;border-radius:8px;padding:13px 24px;font-weight:800;text-transform:uppercase;cursor:pointer;}
.auth-alert{border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:13px;}
.auth-alert.success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#7de2a0;}
.auth-alert.error{background:rgba(232,68,90,.08);border:1px solid rgba(232,68,90,.28);color:#f0849a;}
.auth-card-footer{padding:16px 30px;border-top:1px solid rgba(255,255,255,.07);background:#16161d;text-align:center;font-size:13px;color:#6b6b7e;}
.auth-card-footer a{color:#f5b800;text-decoration:none;}
</style>

<div class="sub-banner"><div class="container"><h6>Forgot Password</h6></div></div>

<section class="auth-page">
    <div class="container">
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="auth-card-body">
                    <div class="auth-heading">
                        <h2>Reset your password</h2>
                        <p>Enter your account email and we'll send you a reset link.</p>
                    </div>

                    @if (session('status'))
                        <div class="auth-alert success">{{ session('status') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="auth-alert error">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="auth-field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                        </div>

                        <button type="submit" class="auth-submit">Send reset link</button>
                    </form>
                </div>

                <div class="auth-card-footer">
                    Remembered it? <a href="{{ route('front.customer.login') }}">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
