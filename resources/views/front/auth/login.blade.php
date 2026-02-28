@extends('front.layout.master')

@section('content')

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');
:root{--bg:#060608;--surface:#0e0e12;--surface2:#16161d;--border:rgba(255,255,255,0.07);--gold:#f5b800;--gold-d:#c99300;--text:#e8e8ef;--muted:#6b6b7e;--red:#e8445a;--green:#22c55e;--radius:14px;--fh:'Syne',sans-serif;--fb:'DM Sans',sans-serif;}

.auth-page{background:var(--bg);min-height:100vh;font-family:var(--fb);color:var(--text);padding:64px 0 80px;display:flex;align-items:center;}

.auth-wrap{max-width:460px;margin:0 auto;width:100%;padding:0 16px;}

/* Glow bg */
.auth-glow{position:fixed;top:0;left:0;right:0;bottom:0;pointer-events:none;z-index:0;
    background:radial-gradient(ellipse 60% 50% at 50% 0%,rgba(245,184,0,0.06) 0%,transparent 70%);}

.auth-inner{position:relative;z-index:1;}

/* Logo / brand */
.auth-brand{text-align:center;margin-bottom:32px;}
.auth-brand-icon{width:52px;height:52px;border-radius:14px;background:rgba(245,184,0,.12);border:1px solid rgba(245,184,0,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;}
.auth-brand-title{font-family:var(--fh);font-size:22px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.auth-brand-sub{font-size:13px;color:var(--muted);margin-top:4px;}

/* Card */
.auth-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:relative;}
.auth-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,var(--gold) 40%,var(--gold-d) 70%,transparent);}

.auth-card-body{padding:32px 30px 28px;}

.auth-heading{margin-bottom:26px;}
.auth-heading h2{font-family:var(--fh);font-size:20px;font-weight:800;color:#fff;margin:0 0 5px;letter-spacing:-.3px;}
.auth-heading p{font-size:13px;color:var(--muted);margin:0;}

/* Field */
.auth-field{margin-bottom:16px;}
.auth-field label{display:block;font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:7px;}
.auth-field input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--fb);font-size:14px;padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;}
.auth-field input::placeholder{color:#3a3a4a;}
.auth-field input:focus{border-color:var(--gold-d);box-shadow:0 0 0 3px rgba(245,184,0,.1);}
.auth-field input.is-invalid{border-color:var(--red);}
.auth-error{font-size:12px;color:var(--red);margin-top:5px;}

/* Submit */
.auth-submit{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:var(--gold);color:#000;font-family:var(--fh);font-size:13px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;border:none;border-radius:8px;padding:13px 24px;cursor:pointer;transition:background .2s,transform .1s;margin-top:6px;}
.auth-submit:hover{background:#ffc820;}
.auth-submit:active{transform:scale(.99);}

/* Footer */
.auth-card-footer{padding:16px 30px;border-top:1px solid var(--border);background:var(--surface2);text-align:center;font-size:13px;color:var(--muted);}
.auth-card-footer a{color:var(--gold);text-decoration:none;font-weight:500;}
.auth-card-footer a:hover{text-decoration:underline;}

/* Error alert */
.auth-alert{background:rgba(232,68,90,.08);border:1px solid rgba(232,68,90,.28);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#f0849a;display:flex;align-items:flex-start;gap:8px;}
</style>

<div class="auth-glow"></div>

<div class="sub-banner"><div class="container"><h6>Customer Login</h6></div></div>

<section class="auth-page">
    <div class="container">
        <div class="auth-wrap">
            <div class="auth-inner">

                <div class="auth-brand">
                    <div class="auth-brand-icon">🎫</div>
                    <div class="auth-brand-title">Welcome Back</div>
                    <div class="auth-brand-sub">Sign in to your TKTHouse account</div>
                </div>

                <div class="auth-card">
                    <div class="auth-card-body">

                        <div class="auth-heading">
                            <h2>Sign In</h2>
                            <p>Enter your credentials to continue</p>
                        </div>

                        @if($errors->any())
                            <div class="auth-alert">
                                <i class="fa fa-circle-exclamation" style="margin-top:1px;flex-shrink:0;"></i>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif

                        <form action="{{ route('front.customer.login.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">

                            <div class="auth-field">
                                <label>Email or Username</label>
                                <input type="text" name="login" value="{{ old('login') }}"
                                    placeholder="you@example.com"
                                    class="{{ $errors->has('login') ? 'is-invalid' : '' }}"
                                    required autofocus>
                                @error('login')<div class="auth-error">{{ $message }}</div>@enderror
                            </div>

                            <div class="auth-field">
                                <label>Password</label>
                                <input type="password" name="password"
                                    placeholder="••••••••"
                                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                                    required>
                                @error('password')<div class="auth-error">{{ $message }}</div>@enderror
                            </div>

                            <button class="auth-submit" type="submit">
                                Sign In →
                            </button>
                        </form>
                    </div>

                    <div class="auth-card-footer">
                        New here? <a href="{{ route('front.customer.register', ['redirect' => request('redirect')]) }}">Create an account</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

@endsection
