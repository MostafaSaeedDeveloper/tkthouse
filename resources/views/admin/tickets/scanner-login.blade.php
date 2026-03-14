<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scanner Login — TKT House</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        #080808;
      --surface:   #111111;
      --border:    #222222;
      --accent:    #FFC815;
      --accent2:   #FF3C3C;
      --text:      #F0F0F0;
      --muted:     #666666;
      --input-bg:  #161616;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html {
      height: 100%;
      background: var(--bg);
      overflow-x: hidden;
    }

    body {
      height: 100%;
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      overflow-x: hidden;
      /* overflow-y controlled by JS below */
    }

    /* ── Animated grid background ── */
    .bg-grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(rgba(255,200,21,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,200,21,.04) 1px, transparent 1px);
      background-size: 60px 60px;
      animation: gridShift 20s linear infinite;
    }
    @keyframes gridShift {
      0%   { background-position: 0 0; }
      100% { background-position: 60px 60px; }
    }

    /* ── Glowing orbs ── */
    .orb {
      position: fixed; border-radius: 50%;
      filter: blur(120px); pointer-events: none; z-index: 0;
    }
    .orb-1 {
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(255,200,21,.1), transparent 70%);
      top: -150px; left: -100px;
      animation: float1 8s ease-in-out infinite alternate;
    }
    .orb-2 {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(255,60,60,.07), transparent 70%);
      bottom: -100px; right: -50px;
      animation: float2 10s ease-in-out infinite alternate;
    }
    @keyframes float1 { to { transform: translate(40px, 30px); } }
    @keyframes float2 { to { transform: translate(-30px, -40px); } }

    /* ── Scanlines overlay ── */
    .scanlines {
      position: fixed; inset: 0; z-index: 1; pointer-events: none;
      background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,0,0,.18) 2px,
        rgba(0,0,0,.18) 4px
      );
    }

    /* ── Layout ── */
    .wrapper {
      position: relative; z-index: 2;
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
    }

    /* ── Left panel ── */
    .panel-left {
      display: flex; flex-direction: column;
      justify-content: space-between;
      padding: 48px;
      border-right: 1px solid var(--border);
      position: relative; overflow: hidden;
    }

    .brand {
      display: flex; align-items: center; gap: 14px;
    }
    .brand-logo {
      height: 36px; width: auto;
      filter: brightness(1);
    }

    .left-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }

    .big-label {
      font-family: 'Bebas Neue', cursive;
      font-size: clamp(64px, 7vw, 96px);
      line-height: .95;
      letter-spacing: 2px;
      color: var(--text);
      margin-bottom: 24px;
    }
    .big-label .hl { color: var(--accent); }
    .big-label .outline {
      -webkit-text-stroke: 1px rgba(255,200,21,.3);
      color: transparent;
    }

    .tagline {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: var(--muted);
      border-left: 2px solid var(--accent);
      padding-left: 14px;
      line-height: 1.8;
    }

    /* Decorative QR hint */
    .qr-deco {
      display: grid; grid-template-columns: repeat(5,1fr); gap: 4px;
      width: 80px; margin-top: 48px;
    }
    .qr-deco span {
      aspect-ratio: 1;
      background: var(--accent);
      opacity: 0;
      animation: qrReveal .3s ease forwards;
    }
    .qr-deco span:nth-child(1)  { animation-delay: .4s; }
    .qr-deco span:nth-child(2)  { animation-delay: .5s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(3)  { animation-delay: .6s; }
    .qr-deco span:nth-child(4)  { animation-delay: .45s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(5)  { animation-delay: .55s; }
    .qr-deco span:nth-child(6)  { animation-delay: .7s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(7)  { animation-delay: .65s; }
    .qr-deco span:nth-child(8)  { animation-delay: .8s; }
    .qr-deco span:nth-child(9)  { animation-delay: .75s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(10) { animation-delay: .9s; }
    .qr-deco span:nth-child(11) { animation-delay: .85s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(12) { animation-delay: .95s; }
    .qr-deco span:nth-child(13) { animation-delay: 1s; }
    .qr-deco span:nth-child(14) { animation-delay: 1.05s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(15) { animation-delay: 1.1s; }
    .qr-deco span:nth-child(16) { animation-delay: 1.15s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(17) { animation-delay: 1.2s; }
    .qr-deco span:nth-child(18) { animation-delay: 1.25s; }
    .qr-deco span:nth-child(19) { animation-delay: 1.3s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(20) { animation-delay: 1.35s; }
    .qr-deco span:nth-child(21) { animation-delay: 1.4s; }
    .qr-deco span:nth-child(22) { animation-delay: 1.45s; opacity: 0; background: transparent; }
    .qr-deco span:nth-child(23) { animation-delay: 1.5s; }
    .qr-deco span:nth-child(24) { animation-delay: 1.55s; }
    .qr-deco span:nth-child(25) { animation-delay: 1.6s; opacity: 0; background: transparent; }
    @keyframes qrReveal { to { opacity: 1; } }

    .left-footer {
      font-family: 'DM Mono', monospace;
      font-size: 10px;
      color: var(--muted);
      letter-spacing: 2px;
      text-transform: uppercase;
    }
    .left-footer span {
      display: inline-block;
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      margin-right: 8px;
      animation: pulse 1.5s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: .4; transform: scale(.7); }
    }

    /* ── Right panel (form) ── */
    .panel-right {
      display: flex; align-items: center; justify-content: center;
      padding: 48px;
    }

    .form-card {
      width: 100%; max-width: 400px;
      animation: slideUp .6s cubic-bezier(.22,1,.36,1) both;
      animation-delay: .2s;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .form-header { margin-bottom: 40px; }
    .form-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 10px;
      letter-spacing: 4px;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 12px;
      display: flex; align-items: center; gap: 10px;
    }
    .form-eyebrow::before {
      content: '';
      display: block; width: 24px; height: 1px;
      background: var(--accent);
    }
    .form-title {
      font-family: 'Bebas Neue', cursive;
      font-size: 42px; letter-spacing: 2px;
      line-height: 1;
    }
    .form-subtitle {
      font-size: 13px; color: var(--muted);
      margin-top: 8px; line-height: 1.5;
    }

    /* Error alert */
    .alert-error {
      background: rgba(255,60,60,.08);
      border: 1px solid rgba(255,60,60,.3);
      border-left: 3px solid var(--accent2);
      padding: 12px 16px;
      border-radius: 2px;
      font-size: 13px;
      color: #ff9090;
      margin-bottom: 24px;
      font-family: 'DM Mono', monospace;
    }

    /* Fields */
    .field { margin-bottom: 20px; }

    .field-label {
      display: block;
      font-family: 'DM Mono', monospace;
      font-size: 10px;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .field-wrap {
      position: relative;
    }
    .field-icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      width: 16px; height: 16px; color: var(--muted);
      pointer-events: none;
    }

    .field-input {
      width: 100%;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-bottom: 1px solid #333;
      color: var(--text);
      font-family: 'DM Mono', monospace;
      font-size: 14px;
      padding: 13px 14px 13px 42px;
      outline: none;
      border-radius: 0;
      transition: border-color .2s, box-shadow .2s;
      -webkit-appearance: none;
    }
    .field-input::placeholder { color: #333; }
    .field-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px var(--accent), inset 0 0 20px rgba(255,200,21,.04);
    }
    .field-input:focus + .field-line { transform: scaleX(1); }

    /* Submit */
    .btn-submit {
      width: 100%;
      background: var(--accent);
      color: #080808;
      border: none; cursor: pointer;
      font-family: 'Bebas Neue', cursive;
      font-size: 20px;
      letter-spacing: 3px;
      padding: 16px;
      margin-top: 8px;
      position: relative; overflow: hidden;
      transition: transform .15s, box-shadow .2s;
    }
    .btn-submit::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
      opacity: 0; transition: opacity .2s;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(255,200,21,.35);
    }
    .btn-submit:hover::before { opacity: 1; }
    .btn-submit:active { transform: translateY(0); }

    /* Ripple on click */
    .btn-submit .ripple {
      position: absolute; border-radius: 50%;
      background: rgba(255,255,255,.3);
      transform: scale(0); animation: rippleAnim .5s linear;
      pointer-events: none;
    }
    @keyframes rippleAnim {
      to { transform: scale(4); opacity: 0; }
    }

    .btn-arrow {
      display: inline-block;
      margin-left: 8px; vertical-align: middle;
      transition: transform .2s;
    }
    .btn-submit:hover .btn-arrow { transform: translateX(4px); }

    .form-logo {
      height: 30px; width: auto;
      margin-bottom: 28px;
      display: none;
    }
    .gate-badge {
      display: flex; align-items: center; gap: 10px;
      margin-top: 28px;
      padding: 12px 16px;
      background: rgba(255,200,21,.04);
      border: 1px solid rgba(255,200,21,.12);
    }
    .gate-badge-icon {
      width: 32px; height: 32px;
      border: 1px solid rgba(255,200,21,.3);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .gate-badge-icon svg { width: 16px; height: 16px; color: var(--accent); }
    .gate-badge-text {
      font-family: 'DM Mono', monospace;
      font-size: 10px; letter-spacing: 1.5px;
      text-transform: uppercase; color: var(--muted);
      line-height: 1.6;
    }
    .gate-badge-text strong { color: rgba(255,200,21,.7); display: block; }

    /* Ticker at bottom of form */
    .ticker-wrap {
      margin-top: 32px;
      border-top: 1px solid var(--border);
      padding-top: 16px;
      overflow: hidden;
    }
    .ticker {
      display: flex; gap: 40px;
      animation: tickerScroll 12s linear infinite;
      white-space: nowrap;
    }
    .ticker-item {
      font-family: 'DM Mono', monospace;
      font-size: 10px; letter-spacing: 3px;
      text-transform: uppercase; color: #2a2a2a;
      flex-shrink: 0;
    }
    .ticker-dot {
      display: inline-block; width: 4px; height: 4px;
      background: var(--accent); border-radius: 50%;
      margin: 0 10px; vertical-align: middle; opacity: .4;
    }
    @keyframes tickerScroll {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* ── Responsive ── */
    @media (max-width: 820px) {
      .wrapper {
        grid-template-columns: 1fr;
        min-height: 100dvh;
        height: auto;
        overflow-x: hidden;
      }
      .panel-left { display: none; }

      .panel-right {
        padding: 52px 24px 48px;
        align-items: flex-start;
        justify-content: flex-start;
        min-height: 100dvh;
        height: auto;
        overflow-x: hidden;
        width: 100%;
      }

      .form-card {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
      }

      .form-logo {
        display: block;
        height: 28px;
        margin-bottom: 32px;
      }

      .form-header { margin-bottom: 32px; }
      .form-title  { font-size: 38px; }

      .btn-submit {
        padding: 18px;
        font-size: 18px;
        min-height: 56px;
      }

      .gate-badge  {
        margin-top: 24px;
        /* prevent text overflow */
        word-break: break-word;
      }

      .gate-badge-text { font-size: 9px; }

      .ticker-wrap {
        margin-top: 24px;
        max-width: 100%;
        overflow: hidden;
      }

      .ticker {
        /* slow down slightly on mobile */
        animation-duration: 16s;
      }
    }

    @media (max-width: 380px) {
      .panel-right { padding: 40px 20px 40px; }
      .form-title  { font-size: 32px; }
    }
  </style>
</head>
<body>
  <div class="bg-grid"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="scanlines"></div>

  <div class="wrapper">
    <!-- ── Left branding panel ── -->
    <div class="panel-left">
      <div class="brand">
        <img src="https://tkthouse.com/images/logo-light.png" alt="TKT House" class="brand-logo">
      </div>

      <div class="left-content">
        <div class="big-label">
          GATE<br>
          <span class="hl">ACCESS</span><br>
          <span class="outline">CONTROL</span>
        </div>
        <div class="tagline">
          Gate Team Portal<br>
          QR Scanner Interface<br>
          Authorized Personnel Only
        </div>
        <div class="qr-deco">
          <span></span><span></span><span></span><span></span><span></span>
          <span></span><span></span><span></span><span></span><span></span>
          <span></span><span></span><span></span><span></span><span></span>
          <span></span><span></span><span></span><span></span><span></span>
          <span></span><span></span><span></span><span></span><span></span>
        </div>
      </div>

      <div class="left-footer">
        <span></span>System Online &nbsp;·&nbsp; tkthouse.com
      </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="panel-right">
      <div class="form-card">
        <div class="form-header">
          <img src="https://tkthouse.com/images/logo-light.png" alt="TKT House" class="form-logo">
          <div class="form-eyebrow">Gate Access</div>
          <div class="form-title">Scanner Login</div>
          <div class="form-subtitle">Gate team only — authenticate to launch QR scanner</div>
        </div>

        @if($errors->any())
          <div class="alert-error">⚠ {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('front.scanner.login.submit') }}">
          @csrf

          <div class="field">
            <label class="field-label" for="username">Username</label>
            <div class="field-wrap">
              <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input
                id="username"
                type="text"
                class="field-input"
                name="username"
                value="{{ old('username') }}"
                placeholder="Enter username"
                autocomplete="username"
                required
              >
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="password">Password</label>
            <div class="field-wrap">
              <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input
                id="password"
                type="password"
                class="field-input"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
              >
            </div>
          </div>

          <button class="btn-submit" type="submit" id="submitBtn">
            Open Scanner <span class="btn-arrow">→</span>
          </button>
        </form>

        <div class="gate-badge">
          <div class="gate-badge-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3z"/>
              <path d="M15 15h2v2h-2zM17 17h2v2h-2zM19 15h2v2h-2zM15 19h2v2h-2zM19 19h2v2h-2z"/>
            </svg>
          </div>
          <div class="gate-badge-text">
            <strong>Secure Access Terminal</strong>
            Credentials are session-based &amp; expire after each event
          </div>
        </div>

        <div class="ticker-wrap">
          <div class="ticker">
            <span class="ticker-item">TKT House <span class="ticker-dot"></span> Gate Control <span class="ticker-dot"></span> QR Scanner <span class="ticker-dot"></span> Event Access <span class="ticker-dot"></span> Authorized Only</span>
            <span class="ticker-item">TKT House <span class="ticker-dot"></span> Gate Control <span class="ticker-dot"></span> QR Scanner <span class="ticker-dot"></span> Event Access <span class="ticker-dot"></span> Authorized Only</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Fix overflow: desktop = hidden (no scroll), mobile = auto (scrollable)
    function applyOverflow() {
      if (window.innerWidth <= 820) {
        document.documentElement.style.overflow = 'auto';
        document.body.style.overflow = 'auto';
        document.documentElement.style.height = 'auto';
        document.body.style.height = 'auto';
      } else {
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        document.documentElement.style.height = '100%';
        document.body.style.height = '100%';
      }
    }
    applyOverflow();
    window.addEventListener('resize', applyOverflow);

    // Ripple effect on submit button
    document.getElementById('submitBtn').addEventListener('click', function(e) {
      const btn = this;
      const circle = document.createElement('span');
      const diameter = Math.max(btn.clientWidth, btn.clientHeight);
      const radius = diameter / 2;
      const rect = btn.getBoundingClientRect();
      circle.style.cssText = `
        width: ${diameter}px; height: ${diameter}px;
        left: ${e.clientX - rect.left - radius}px;
        top: ${e.clientY - rect.top - radius}px;
      `;
      circle.classList.add('ripple');
      const existing = btn.querySelector('.ripple');
      if (existing) existing.remove();
      btn.appendChild(circle);
    });
  </script>
</body>
</html>
