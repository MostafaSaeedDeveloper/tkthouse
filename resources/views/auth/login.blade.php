<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Sign In | TKT House</title>
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" id="css-main" href="{{ asset('admin/assets/css/dashmix.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
    :root {
        --bg:       #060608;
        --surface:  #0e0e12;
        --surface2: #16161d;
        --border:   rgba(255,255,255,0.07);
        --gold:     #f5b800;
        --gold-dim: #c99300;
        --text:     #e8e8ef;
        --muted:    #6b6b7e;
        --red:      #e8445a;
        --radius:   12px;
        --font-h:   'Syne', sans-serif;
        --font-b:   'DM Sans', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background: var(--bg);
        font-family: var(--font-b);
        color: var(--text);
        min-height: 100vh;
        overflow: hidden;
    }

    /* ── Layout ── */
    .lg-wrap {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 100vh;
    }
    @media (max-width: 768px) {
        .lg-wrap { grid-template-columns: 1fr; }
        .lg-right  { display: none; }
    }

    /* ── Left panel ── */
    .lg-left {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 48px 40px;
        background: var(--surface);
        position: relative;
        z-index: 2;
    }
    .lg-left::after {
        content: '';
        position: absolute;
        right: 0; top: 0; bottom: 0;
        width: 1px;
        background: linear-gradient(to bottom, transparent, rgba(245,184,0,0.3) 40%, rgba(245,184,0,0.3) 60%, transparent);
    }

    /* noise texture overlay */
    .lg-left::before {
        content: '';
        position: absolute; inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
        background-size: 200px;
        pointer-events: none;
        opacity: 0.4;
    }

    .lg-inner { width: 100%; max-width: 380px; position: relative; }

    /* Logo */
    .lg-logo {
        font-family: var(--font-h);
        font-size: 38px;
        font-weight: 800;
        letter-spacing: -1px;
        margin-bottom: 8px;
        line-height: 1;
    }
    .lg-logo a { text-decoration: none; }
    .lg-logo .t1 { color: #fff; }
    .lg-logo .t2 { color: var(--gold); }

    .lg-tagline {
        font-family: var(--font-h);
        font-size: 10px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 40px;
    }

    /* Divider label */
    .lg-section-label {
        font-family: var(--font-h);
        font-size: 10px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: var(--gold);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 22px;
    }
    .lg-section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    /* Form */
    .lg-field { margin-bottom: 14px; }
    .lg-field label {
        display: block;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 7px;
    }
    .lg-field input {
        width: 100%;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-family: var(--font-b);
        font-size: 14px;
        padding: 13px 16px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .lg-field input::placeholder { color: var(--muted); }
    .lg-field input:focus {
        border-color: var(--gold-dim);
        box-shadow: 0 0 0 3px rgba(245,184,0,0.10);
    }
    .lg-field input.is-invalid { border-color: var(--red); }
    .lg-field .invalid-feedback { display: block; font-size: 12px; color: #f0849a; margin-top: 6px; }

    /* Submit */
    .lg-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        background: var(--gold);
        color: #000;
        font-family: var(--font-h);
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.5px;
        border: none;
        border-radius: 8px;
        padding: 15px 24px;
        cursor: pointer;
        transition: background 0.2s, transform 0.1s;
        margin-top: 8px;
    }
    .lg-btn:hover { background: #ffc820; }
    .lg-btn:active { transform: scale(0.99); }

    /* Alert for general errors */
    .lg-alert {
        background: rgba(232,68,90,0.08);
        border: 1px solid rgba(232,68,90,0.3);
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 13px;
        color: #f0849a;
        margin-bottom: 18px;
    }

    /* Footer link */
    .lg-back {
        margin-top: 28px;
        text-align: center;
        font-size: 12px;
        color: var(--muted);
    }
    .lg-back a {
        color: var(--gold);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    .lg-back a:hover { color: #ffc820; }

    /* ── Right panel ── */
    .lg-right {
        position: relative;
        background: var(--bg);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 48px;
    }

    /* Ambient glow blobs */
    .blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(90px);
        opacity: 0.18;
        pointer-events: none;
    }
    .blob-1 { width: 420px; height: 420px; background: var(--gold); top: -80px; right: -80px; animation: float1 8s ease-in-out infinite; }
    .blob-2 { width: 280px; height: 280px; background: #ff8c00; bottom: 60px; left: 20px; animation: float2 10s ease-in-out infinite; opacity: 0.10; }
    @keyframes float1 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-30px,30px)} }
    @keyframes float2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(20px,-20px)} }

    /* Grid pattern */
    .lg-grid {
        position: absolute; inset: 0;
        background-image:
            linear-gradient(rgba(245,184,0,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(245,184,0,0.04) 1px, transparent 1px);
        background-size: 50px 50px;
        mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 0%, transparent 100%);
    }

    /* Center text */
    .lg-right-content { position: relative; z-index: 2; text-align: center; max-width: 360px; }
    .lg-right-eyebrow {
        font-family: var(--font-h);
        font-size: 10px;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    .lg-right-eyebrow::before,
    .lg-right-eyebrow::after { content: ''; width: 30px; height: 1px; background: rgba(245,184,0,0.4); }

    .lg-right h2 {
        font-family: var(--font-h);
        font-size: clamp(28px, 3.5vw, 42px);
        font-weight: 800;
        color: #fff;
        line-height: 1.15;
        letter-spacing: -1px;
        margin-bottom: 18px;
    }
    .lg-right h2 span { color: var(--gold); }

    .lg-right p {
        font-size: 14px;
        color: var(--muted);
        line-height: 1.7;
        margin-bottom: 32px;
    }

    /* Stats row */
    .lg-stats { display: flex; justify-content: center; gap: 32px; }
    .lg-stat { text-align: center; }
    .lg-stat-num {
        font-family: var(--font-h);
        font-size: 26px;
        font-weight: 800;
        color: var(--gold);
        line-height: 1;
        margin-bottom: 4px;
    }
    .lg-stat-lbl { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); }

    /* Divider for stats */
    .lg-stats-sep { width: 1px; background: var(--border); }

    /* Animate in */
    .lg-inner > * { animation: fadeUp 0.5s ease both; }
    .lg-inner > *:nth-child(1) { animation-delay: 0.05s; }
    .lg-inner > *:nth-child(2) { animation-delay: 0.10s; }
    .lg-inner > *:nth-child(3) { animation-delay: 0.15s; }
    .lg-inner > *:nth-child(4) { animation-delay: 0.20s; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    </style>
</head>
<body>

<div class="lg-wrap">

    {{-- ── Left: Form ── --}}
    <div class="lg-left">
        <div class="lg-inner">

            {{-- Logo --}}
            <div class="lg-logo">
                <a href="{{ route('front.home') }}">
                    <img style="height: 50px" src="{{asset('images/logo-light.png')}}" alt="">
                </a>
            </div>
            <p class="lg-tagline">Dashboard Sign In</p>

            {{-- Section label --}}
            <div class="lg-section-label">Admin Access</div>

            {{-- Errors --}}
            @if($errors->has('email') || $errors->has('auth'))
                <div class="lg-alert">
                    {{ $errors->first('email') ?: $errors->first('auth') }}
                </div>
            @endif

            {{-- Form --}}
            <form action="{{ route('login') }}" method="POST" autocomplete="off">
                @csrf

                <div class="lg-field">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="{{ old('username') }}"
                        placeholder="Enter your username"
                        class="@error('username') is-invalid @enderror"
                        required
                        autofocus
                    >
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="lg-field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        class="@error('password') is-invalid @enderror"
                        required
                    >
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="lg-btn">
                    Sign In &nbsp;→
                </button>
            </form>

            <div class="lg-back">
                <a href="{{ route('front.home') }}">← Back to website</a>
            </div>

        </div>
    </div>

    {{-- ── Right: Branding ── --}}
    <div class="lg-right">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="lg-grid"></div>

        <div class="lg-right-content">

            </div>
        </div>
    </div>

</div>

<script src="{{ asset('admin/assets/js/dashmix.app.min.js') }}"></script>
</body>
</html>
