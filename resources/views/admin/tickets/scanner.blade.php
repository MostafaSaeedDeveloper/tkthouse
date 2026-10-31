<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#060608">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>TKT Scanner</title>
  @include('partials.favicon')
<style>
/* Self-contained on purpose: no CDN fonts, icon fonts or scripts, so the gate works on a poor connection. */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:#060608; --surface:#0e0e12; --surface2:#16161d; --border:rgba(255,255,255,.1);
  --gold:#FFD200; --text:#f4f4f8; --muted:#9a9ab0;
  --green:#16a34a; --green-bg:#052e16; --red:#dc2626; --red-bg:#3a0a0a; --amber:#f59e0b; --amber-bg:#3a2600;
  --sat: env(safe-area-inset-top, 0px); --sab: env(safe-area-inset-bottom, 0px); --sal: env(safe-area-inset-left, 0px); --sar: env(safe-area-inset-right, 0px);
}
html, body { height:100%; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased; -webkit-text-size-adjust:100%; overscroll-behavior:none; }
[hidden] { display:none !important; }
button, input, select { font:inherit; color:inherit; }
button { cursor:pointer; -webkit-tap-highlight-color:transparent; touch-action:manipulation; }
svg.i { width:1em; height:1em; fill:none; stroke:currentColor; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; flex:none; }

.app { min-height:100dvh; display:flex; flex-direction:column; gap:10px; padding:calc(8px + var(--sat)) calc(12px + var(--sar)) calc(12px + var(--sab)) calc(12px + var(--sal)); max-width:760px; margin:0 auto; }

/* header */
.top { display:flex; align-items:center; gap:8px; }
.top img { height:26px; }
.top .grow { flex:1; min-width:0; }
.who { font-size:12px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:6px 10px; border-radius:99px; border:1px solid var(--border); background:var(--surface2); white-space:nowrap; min-height:32px; }
.pill .dot { width:9px; height:9px; border-radius:50%; background:var(--muted); }
.pill.ok .dot { background:#22c55e; } .pill.bad .dot { background:var(--red); } .pill.warn .dot { background:var(--amber); }
.pill.bad { border-color:var(--red); } .pill.warn { border-color:var(--amber); }
.iconbtn { width:44px; height:44px; border-radius:12px; border:1px solid var(--border); background:var(--surface2); display:grid; place-items:center; font-size:20px; }
.iconbtn[aria-pressed="true"] { border-color:var(--gold); color:var(--gold); }
.iconbtn[hidden] { display:none; }

.event { background:var(--gold); color:#0d0d10; border-radius:14px; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.event h1 { font-size:clamp(18px,5.5vw,26px); font-weight:900; line-height:1.15; letter-spacing:-.3px; flex:1; min-width:0; overflow-wrap:anywhere; }
.event select { background:#0d0d10; color:var(--gold); border:0; border-radius:10px; min-height:44px; padding:0 10px; font-weight:800; max-width:100%; width:100%; font-size:16px; }
.event .staff { font-size:13px; font-weight:700; opacity:.8; margin-top:2px; overflow-wrap:anywhere; }
.event .tag { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; opacity:.75; }

.stats { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
.stat { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:6px 4px; text-align:center; }
.stat b { display:block; font-size:22px; line-height:1.1; font-variant-numeric:tabular-nums; }
.stat span { display:flex; align-items:center; justify-content:center; gap:4px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
.stat.g b { color:#4ade80; } .stat.r b { color:#f87171; } .stat.a b { color:#fbbf24; }

/* main grid: stacked on portrait, side by side on landscape / tablets */
.main { display:grid; gap:10px; flex:1; grid-template-columns:minmax(0,1fr); align-content:start; }
.main > * { min-width:0; }
@media (orientation:landscape) and (min-width:640px) { .main { grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); } .app { max-width:1100px; } }

.cam { position:relative; border-radius:18px; overflow:hidden; background:#000; border:2px solid var(--border); aspect-ratio:1/1; max-height:62dvh; width:100%; justify-self:center; }
@media (orientation:landscape) { .cam { max-height:calc(100dvh - 190px); } }
.cam #reader { position:absolute; inset:0; border:0 !important; }
.cam #reader video { width:100% !important; height:100% !important; object-fit:cover; }
.cam #reader img, .cam #reader__dashboard, .cam #reader__status_span { display:none !important; }
.cam #reader__scan_region { background:transparent !important; min-height:0 !important; }
.frame { position:absolute; inset:0; display:grid; place-items:center; pointer-events:none; z-index:3; }
.frame div { width:68%; aspect-ratio:1/1; border-radius:18px; border:4px solid var(--gold); box-shadow:0 0 0 100vmax rgba(0,0,0,.35); }
.camtools { position:absolute; right:10px; bottom:10px; display:flex; gap:8px; z-index:5; }
.camtools .iconbtn { background:rgba(0,0,0,.65); }
.camstate { position:absolute; left:10px; top:10px; z-index:5; }
.camfail { position:absolute; inset:0; z-index:4; display:none; flex-direction:column; align-items:center; justify-content:center; gap:12px; padding:20px; text-align:center; background:var(--surface); font-weight:700; }
.camfail.show { display:flex; }
.camfail small { color:var(--muted); font-weight:500; font-size:14px; }

.btn { min-height:56px; border-radius:14px; border:0; font-weight:900; font-size:18px; padding:0 18px; display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; }
.btn.gold { background:var(--gold); color:#0d0d10; }
.btn.ghost { background:var(--surface2); border:1px solid var(--border); }
.btn.green { background:var(--green); color:#fff; min-height:72px; font-size:22px; }
.btn:disabled { opacity:.45; cursor:not-allowed; }

.panel { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:12px; display:flex; flex-direction:column; gap:10px; }
.row { display:flex; gap:8px; }
.field { flex:1; min-height:56px; border-radius:14px; background:var(--surface2); border:1px solid var(--border); padding:0 14px; font-size:18px; min-width:0; }
.field:focus { outline:2px solid var(--gold); outline-offset:1px; }
.switch { display:flex; align-items:center; justify-content:space-between; gap:12px; min-height:48px; font-weight:700; }
.switch small { display:block; color:var(--muted); font-weight:500; font-size:12px; }
.switch input { width:44px; height:44px; accent-color:var(--gold); flex:none; }
.hist { list-style:none; display:flex; flex-direction:column; gap:4px; font-size:13px; color:var(--muted); }
.hist li { display:flex; gap:8px; align-items:center; }
.hist b { color:var(--text); font-variant-numeric:tabular-nums; }

/* result: unmistakable full-width state */
.result { position:fixed; inset:0; z-index:50; display:none; flex-direction:column; align-items:center; justify-content:center; gap:14px; text-align:center; padding:calc(20px + var(--sat)) calc(20px + var(--sar)) calc(20px + var(--sab)) calc(20px + var(--sal)); overflow:auto; }
.result.show { display:flex; }
.result.green { background:var(--green-bg); } .result.red { background:var(--red-bg); } .result.amber { background:var(--amber-bg); }
.result .big { width:min(34vmin,200px); height:min(34vmin,200px); border-radius:50%; display:grid; place-items:center; font-size:min(22vmin,120px); border:6px solid currentColor; }
.result.green .big { color:#4ade80; } .result.red .big { color:#f87171; } .result.amber .big { color:#fbbf24; }
.result h2 { font-size:clamp(30px,10vmin,64px); font-weight:900; line-height:1.05; letter-spacing:-.5px; }
.result .sub { font-size:clamp(17px,4.5vmin,26px); font-weight:700; max-width:30ch; overflow-wrap:anywhere; }
.result .meta { font-size:clamp(14px,3.6vmin,20px); color:rgba(255,255,255,.8); overflow-wrap:anywhere; }
.result .acts { width:min(100%,460px); display:flex; flex-direction:column; gap:10px; margin-top:6px; }
.result .acts .btn { font-size:20px; }
.result .hint { font-size:13px; color:rgba(255,255,255,.65); }

.banner { border-radius:12px; padding:10px 12px; font-weight:700; font-size:14px; border:1px solid var(--amber); background:var(--amber-bg); display:none; }
.banner.show { display:block; }
.sr { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
@media (prefers-reduced-motion:no-preference) { .result.show { animation:pop .12s ease-out; } @keyframes pop { from { opacity:.4; transform:scale(.98); } to { opacity:1; transform:none; } } }
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M5 5l14 14M19 5L5 19"/></symbol>
  <symbol id="i-ban" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></symbol>
  <symbol id="i-warn" viewBox="0 0 24 24"><path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18.2v.1"/></symbol>
  <symbol id="i-repeat" viewBox="0 0 24 24"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 013-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 01-3 3H3"/></symbol>
  <symbol id="i-flip" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0115-6.7L21 8M21 3v5h-5M21 12a9 9 0 01-15 6.7L3 16M3 21v-5h5"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></symbol>
  <symbol id="i-sound" viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16.5 8.5a5 5 0 010 7M19 6a8.5 8.5 0 010 12"/></symbol>
  <symbol id="i-mute" viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M17 9l5 6M22 9l-5 6"/></symbol>
  <symbol id="i-out" viewBox="0 0 24 24"><path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4M16 17l5-5-5-5M21 12H9"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></symbol>
  <symbol id="i-qr" viewBox="0 0 24 24"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM20 14v.1M14 20h3M20 17v4"/></symbol>
</svg>

<div class="app" id="app">
  <header class="top">
    <img src="{{ asset('images/logo-light.png') }}" alt="TKT">
    <div class="grow"></div>
    <span class="pill" id="netPill" role="status"><span class="dot"></span><span id="netText">Checking…</span></span>
    <button class="iconbtn" id="soundBtn" type="button" aria-pressed="true" aria-label="Sound and vibration"><svg class="i"><use href="#i-sound"/></svg></button>
    <form method="POST" action="{{ route('front.scanner.logout') }}" style="margin:0">@csrf
      <button class="iconbtn" type="submit" aria-label="Log out"><svg class="i"><use href="#i-out"/></svg></button>
    </form>
  </header>

  <section class="event">
    <div style="flex:1;min-width:0">
      <div class="tag">Scanning event</div>
      <h1 id="eventName">…</h1>
      <select id="eventSelect" aria-label="Event" hidden></select>
      <div class="staff" id="who">{{ auth()->user()->name }}@if($scannerRestricted) · assigned: {{ collect($scannerEvents)->pluck('name')->implode(', ') }}@else · all events @endif</div>
    </div>
  </section>

  <div class="banner" id="banner" role="alert"></div>

  <section class="stats" aria-label="This device today">
    <div class="stat g"><b id="cOk">{{ $scannerCheckins }}</b><span><svg class="i"><use href="#i-check"/></svg>Checked in</span></div>
    <div class="stat r"><b id="cDup">0</b><span><svg class="i"><use href="#i-repeat"/></svg>Duplicate</span></div>
    <div class="stat r"><b id="cBad">0</b><span><svg class="i"><use href="#i-x"/></svg>Invalid</span></div>
    <div class="stat r"><b id="cRef">0</b><span><svg class="i"><use href="#i-ban"/></svg>Refunded</span></div>
  </section>

  <main class="main">
    <section class="cam" id="camBox" aria-label="Camera">
      <div id="reader"></div>
      <div class="frame" aria-hidden="true"><div></div></div>
      <span class="pill camstate" id="camPill"><span class="dot"></span><span id="camText">Starting camera…</span></span>
      <div class="camtools">
        <button class="iconbtn" id="torchBtn" type="button" aria-pressed="false" aria-label="Torch" hidden><svg class="i"><use href="#i-bolt"/></svg></button>
        <button class="iconbtn" id="flipBtn" type="button" aria-label="Switch camera" hidden><svg class="i"><use href="#i-flip"/></svg></button>
      </div>
      <div class="camfail" id="camFail">
        <svg class="i" style="font-size:44px;color:var(--amber)"><use href="#i-warn"/></svg>
        <div id="camFailText">Camera unavailable.</div>
        <small>Use manual ticket entry below — it works without the camera.</small>
        <button class="btn ghost" id="camRetry" type="button" style="max-width:260px">Try camera again</button>
      </div>
    </section>

    <section class="panel">
      <div class="switch">
        <div>Fast check-in<small id="modeHelp">Scan → server checks in → next. Off: you confirm each ticket.</small></div>
        <input type="checkbox" id="fastToggle" role="switch" aria-label="Fast check-in">
      </div>
      <form class="row" id="manualForm" autocomplete="off">
        <input class="field" id="manualCode" inputmode="text" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="Type ticket number" aria-label="Ticket number" maxlength="1000">
        <button class="btn gold" type="submit" style="width:auto;min-width:64px" aria-label="Look up ticket"><svg class="i" style="font-size:24px"><use href="#i-search"/></svg></button>
      </form>
      <ul class="hist" id="hist" aria-label="Recent scans"></ul>
    </section>
  </main>
</div>

<div class="result" id="result" role="alertdialog" aria-live="assertive" aria-labelledby="rTitle">
  <div class="big"><svg class="i" id="rIcon"><use href="#i-check"/></svg></div>
  <h2 id="rTitle"></h2>
  <div class="sub" id="rSub"></div>
  <div class="meta" id="rMeta"></div>
  <div class="acts">
    <button class="btn green" id="rConfirm" type="button" hidden>CHECK IN</button>
    <button class="btn gold" id="rVerify" type="button" hidden>Verify ticket status</button>
    <a class="btn gold" id="rLogin" href="{{ route('front.scanner.login') }}" hidden>Log in again</a>
    <button class="btn gold" id="rNext" type="button">Scan next</button>
  </div>
  <div class="hint" id="rHint"></div>
</div>

<script type="application/json" id="cfg">{!! json_encode(['events' => $scannerEvents, 'checkin' => route('admin.tickets.scanner.checkin'), 'lookup' => route('admin.tickets.scanner.lookup'), 'ping' => route('admin.tickets.scanner.ping'), 'initial' => $initialResult ?? null, 'initialCode' => $lastCode ?? null, 'user' => auth()->id()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode-2.3.8.min.js') }}"></script>
<script>
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const cfg = JSON.parse($('cfg').textContent);
  const LS = { get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (_) { return d; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (_) {} } };
  let csrf = document.querySelector('meta[name="csrf-token"]').content;

  // ───────── state ─────────
  const counts = { ok: +$('cOk').textContent || 0, dup: 0, bad: 0, ref: 0 };
  let eventId = null;
  let online = navigator.onLine;
  let busy = false;                 // a check-in/lookup request is in flight
  let overlayOpen = false;
  let pendingCode = null;           // at most one different code waits while busy
  let lastCode = null, lastSeen = 0; // debounce: the same QR staying in view is ONE scan
  let current = null;               // ticket code shown in the overlay (for confirm / verify)
  let autoTimer = null;
  let sound = LS.get('tkt.sound', '1') === '1';
  let fast = LS.get('tkt.fast', '0') === '1';

  // ───────── helpers ─────────
  const svg = (name) => { const u = $('rIcon').querySelector('use'); u.setAttribute('href', '#i-' + name); };
  const fmtTime = (iso) => { try { return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }); } catch (_) { return ''; } };
  const ago = (s) => s == null ? '' : s < 60 ? s + 's ago' : s < 3600 ? Math.round(s / 60) + ' min ago' : Math.round(s / 3600) + ' h ago';
  const bump = (k) => { counts[k]++; $({ ok: 'cOk', dup: 'cDup', bad: 'cBad', ref: 'cRef' }[k]).textContent = counts[k]; };

  const banner = (msg) => { const b = $('banner'); b.textContent = msg || ''; b.classList.toggle('show', !!msg); };

  // audio + haptics (best effort; iOS needs a user gesture before audio, which the first tap provides)
  let actx = null;
  const beep = (seq) => {
    if (!sound) return;
    try {
      actx = actx || new (window.AudioContext || window.webkitAudioContext)();
      if (actx.state === 'suspended') actx.resume();
      let t = actx.currentTime;
      seq.forEach(([f, d]) => { const o = actx.createOscillator(), g = actx.createGain(); o.frequency.value = f; o.type = 'square'; g.gain.value = .12; o.connect(g); g.connect(actx.destination); o.start(t); o.stop(t + d); t += d + .04; });
    } catch (_) {}
  };
  const buzz = (p) => { if (sound && navigator.vibrate) { try { navigator.vibrate(p); } catch (_) {} } };
  const feedback = (kind) => {
    if (kind === 'green') { beep([[880, .12]]); buzz(60); }
    else if (kind === 'red') { beep([[220, .22], [180, .3]]); buzz([200, 80, 200]); }
    else { beep([[440, .15], [440, .15]]); buzz([120, 60, 120]); }
  };
  const paintSound = () => { $('soundBtn').setAttribute('aria-pressed', sound ? 'true' : 'false'); $('soundBtn').querySelector('use').setAttribute('href', sound ? '#i-sound' : '#i-mute'); };
  $('soundBtn').addEventListener('click', () => { sound = !sound; LS.set('tkt.sound', sound ? '1' : '0'); paintSound(); if (sound) beep([[660, .08]]); });
  paintSound();

  // ───────── network state ─────────
  const paintNet = (state, text) => { const p = $('netPill'); p.className = 'pill ' + state; $('netText').textContent = text; };
  const setOnline = (v) => { online = v; paintNet(v ? 'ok' : 'bad', v ? 'Online' : 'OFFLINE'); banner(v ? '' : 'No connection. Tickets cannot be verified — do NOT admit anyone until you are back online.'); };
  window.addEventListener('online', () => ping()); window.addEventListener('offline', () => setOnline(false));
  async function api(url, opts = {}, timeoutMs = 8000) {
    const ctl = new AbortController(); const to = setTimeout(() => ctl.abort(), timeoutMs);
    try {
      const res = await fetch(url, { ...opts, signal: ctl.signal, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } });
      let data = null; try { data = await res.json(); } catch (_) {}
      return { status: res.status, data };
    } finally { clearTimeout(to); }
  }
  async function ping() {
    try {
      const r = await api(cfg.ping, { method: 'GET' }, 6000);
      if (r.status === 401 || r.status === 419) { paintNet('warn', 'Session expired'); showClient('Session expired', 'Log in again to keep scanning.', { login: true }); return; }
      if (r.data && r.data.csrf) csrf = r.data.csrf;
      setOnline(r.status >= 200 && r.status < 300);
    } catch (_) { setOnline(false); }
  }
  setInterval(ping, 20000);

  // ───────── event selection ─────────
  const sel = $('eventSelect');
  (function initEvents() {
    const ev = cfg.events || [];
    if (ev.length === 0) { $('eventName').textContent = 'No event available'; banner('No event is available for this account. Contact the event manager.'); return; }
    const saved = LS.get('tkt.event', '');
    if (ev.length === 1) { eventId = ev[0].id; $('eventName').textContent = ev[0].name; return; }
    sel.hidden = false; $('eventName').hidden = true;
    const ph = document.createElement('option'); ph.value = ''; ph.textContent = 'Select the event you are scanning…'; sel.appendChild(ph);
    ev.forEach((e) => { const o = document.createElement('option'); o.value = e.id; o.textContent = e.name; sel.appendChild(o); });
    if (ev.some((e) => String(e.id) === saved)) sel.value = saved;
    const apply = () => { eventId = sel.value ? +sel.value : null; LS.set('tkt.event', sel.value); banner(eventId || !online ? (online ? '' : $('banner').textContent) : 'Select the event first — scanning is disabled until you do.'); };
    sel.addEventListener('change', apply); apply();
  })();

  // ───────── overlay ─────────
  function show(kind, icon, title, sub, meta, o = {}) {
    clearTimeout(autoTimer);
    const r = $('result');
    r.className = 'result show ' + kind;
    svg(icon);
    $('rTitle').textContent = title; $('rSub').textContent = sub || ''; $('rMeta').textContent = meta || '';
    $('rHint').textContent = o.hint || '';
    $('rConfirm').hidden = !o.confirm; $('rVerify').hidden = !o.verify; $('rLogin').hidden = !o.login;
    $('rNext').textContent = o.nextLabel || 'Scan next';
    overlayOpen = true; current = o.code || null;
    feedback(kind);
    if (o.auto) autoTimer = setTimeout(hide, o.auto);
    (o.confirm ? $('rConfirm') : $('rNext')).focus({ preventScroll: true });
  }
  function hide() {
    clearTimeout(autoTimer);
    $('result').classList.remove('show'); overlayOpen = false; current = null;
    lastSeen = Date.now(); // the QR that is still in front of the lens must not re-fire
    if (pendingCode) { const c = pendingCode; pendingCode = null; handleScan(c); }
  }
  $('rNext').addEventListener('click', hide);
  const showClient = (title, sub, o = {}) => show('amber', 'warn', title, sub, '', { ...o, hint: o.hint || '' });

  function showOutcome(d, code, status) {
    const t = d.ticket || {};
    const holder = t.holder ? t.holder : '';
    const line = [t.type, t.number ? '#' + t.number : ''].filter(Boolean).join(' · ');
    switch (d.code) {
      case 'approved': bump('ok'); addHist('ok', t.number, 'Checked in'); return show('green', 'check', 'ENTRY APPROVED', holder, line, { auto: 1400, nextLabel: 'Scan next' });
      case 'ready': return show('green', 'check', 'VALID TICKET', holder, line, { confirm: true, code, nextLabel: 'Cancel', hint: 'Not checked in yet.' });
      case 'already_checked_in': {
        bump('dup'); addHist('dup', t.number, 'Duplicate');
        const who = t.checked_in_by_you ? 'you' : (t.checked_in_by || 'another scanner');
        const note = t.checked_in_by_you && t.seconds_ago != null && t.seconds_ago < 120 ? 'Checked in by YOU moments ago — most likely your previous scan went through.' : '';
        return show('red', 'repeat', 'ALREADY USED', (holder ? holder + ' — ' : '') + 'checked in ' + fmtTime(t.checked_in_at), 'by ' + who + ' · ' + ago(t.seconds_ago), { code, nextLabel: 'Scan next', hint: note });
      }
      case 'refunded': bump('ref'); addHist('ref', t.number, 'Refunded'); return show('red', 'ban', 'REFUNDED', 'Do not admit', d.message.replace(/^REFUNDED \/ CANCELED — /, '').replace(/\. Entry is not allowed\.$/, ''), { code });
      case 'canceled': bump('ref'); addHist('ref', t.number, 'Canceled'); return show('red', 'ban', 'CANCELED', 'Do not admit', line, { code });
      case 'wrong_event': bump('bad'); addHist('bad', t.number, 'Wrong event'); return show('red', 'x', 'WRONG EVENT', 'Ticket is for: ' + (t.event || '?'), line, { code });
      case 'event_not_allowed': return show('red', 'x', 'NOT ALLOWED', d.message, '', {});
      case 'not_found': bump('bad'); addHist('bad', '—', 'Invalid'); return show('red', 'x', 'INVALID TICKET', 'Not found in the system', 'Try again or type the number.', { code });
      case 'order_not_paid': case 'unknown_status': addHist('bad', t.number, 'Review'); return show('amber', 'warn', 'NEEDS REVIEW', d.message, line, { code, nextLabel: 'Scan next' });
      default: return show('amber', 'warn', 'ERROR', d.message || 'Unexpected response — do not admit.', '', { code, verify: !!code });
    }
  }

  function addHist(kind, num, label) {
    const ul = $('hist'); const li = document.createElement('li');
    const b = document.createElement('b'); b.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const s = document.createElement('span'); s.textContent = label + (num ? ' · ' + num : '');
    li.append(b, s); ul.prepend(li); while (ul.children.length > 5) ul.lastChild.remove(); // capped: no growth over a long shift
  }

  // ───────── server calls ─────────
  // A check-in is never retried automatically: if the outcome is unknown the operator is told so and must verify.
  async function serverCall(kind, code) {
    if (!online) { return showClient('NO CONNECTION', 'Ticket NOT verified. Do not admit.', { verify: true, code, hint: 'Reconnect, then verify the ticket.' }); }
    if (!eventId && (cfg.events || []).length !== 1) { return showClient('SELECT EVENT', 'Choose the event at the top first.', {}); }
    busy = true;
    try {
      const r = await api(kind === 'checkin' ? cfg.checkin : cfg.lookup, { method: 'POST', body: JSON.stringify({ code, event_id: eventId }) });
      if (r.status === 401 || r.status === 419) { return showClient('SESSION EXPIRED', 'Not checked in. Log in again.', { login: true, hint: 'Nothing was recorded for this scan.' }); }
      if (r.status === 429) { return showClient('TOO FAST', 'Wait a moment and scan again. Not checked in.', { code }); }
      if (r.data && r.data.code) { setOnline(true); return showOutcome(r.data, code, r.status); }
      return showClient('RESULT UNKNOWN', 'Server error. Verify before deciding — do not assume entry.', { verify: true, code });
    } catch (_) {
      const timedOut = online;
      if (!timedOut) setOnline(false);
      return showClient(kind === 'checkin' ? 'UNVERIFIED' : 'NETWORK ERROR', kind === 'checkin' ? 'No answer from the server. The ticket may or may not be checked in.' : 'Could not reach the server.', { verify: true, code, hint: 'Tap “Verify” to see the real status. Do not scan again blindly.' });
    } finally { busy = false; }
  }

  $('rConfirm').addEventListener('click', async () => { const c = current; if (!c) return; $('rConfirm').disabled = true; try { await serverCall('checkin', c); } finally { $('rConfirm').disabled = false; } });
  $('rVerify').addEventListener('click', async () => { const c = current; if (!c) return; $('rVerify').disabled = true; try { await serverCall('lookup', c); } finally { $('rVerify').disabled = false; } });

  // A decoded QR (camera) or typed code. Camera in fast mode checks in directly; otherwise it only looks up.
  async function handleScan(code, manual = false) {
    code = (code || '').trim(); if (!code) return;
    if (overlayOpen || busy) { if (!manual && code !== current) pendingCode = code; return; }
    await serverCall(fast && !manual ? 'checkin' : 'lookup', code);
  }

  function onDecode(text) {
    const now = Date.now();
    const same = text === lastCode && now - lastSeen < 1500;   // still in view → same presentation
    lastCode = text; lastSeen = now;
    if (same) return;                                           // no duplicate backend request
    handleScan(text);
  }

  $('manualForm').addEventListener('submit', (e) => { e.preventDefault(); const c = $('manualCode').value; $('manualCode').value = ''; $('manualCode').blur(); handleScan(c, true); });

  const paintMode = () => { $('fastToggle').checked = fast; $('modeHelp').textContent = fast ? 'ON — each scan is checked in by the server immediately.' : 'Off — scan shows the ticket and you tap CHECK IN.'; };
  $('fastToggle').addEventListener('change', (e) => { fast = e.target.checked; LS.set('tkt.fast', fast ? '1' : '0'); paintMode(); });
  paintMode();

  // ───────── camera (started once, kept running) ─────────
  const qr = window.Html5Qrcode ? new Html5Qrcode('reader', { verbose: false, formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] }) : null;
  let cams = [], camIdx = 0, starting = false, running = false, torchOn = false;
  const camPill = (state, text) => { $('camPill').className = 'pill camstate ' + state; $('camText').textContent = text; };
  const camFail = (msg) => { running = false; camPill('bad', 'Camera off'); $('camFailText').textContent = msg; $('camFail').classList.add('show'); $('torchBtn').hidden = true; $('flipBtn').hidden = true; };

  async function startCamera(preferId) {
    if (!qr) return camFail('Scanner library failed to load. Reload the page, or use manual entry.');
    if (starting) return; starting = true;
    $('camFail').classList.remove('show'); camPill('warn', 'Starting camera…');
    try {
      if (running) { try { await qr.stop(); } catch (_) {} running = false; }
      let source = { facingMode: 'environment' }; // html5-qrcode 2.3.8 only accepts a string or {exact}; the string is treated as a preference
      if (preferId) source = { deviceId: { exact: preferId } };
      await qr.start(source, { fps: 10, aspectRatio: 1, disableFlip: true }, onDecode, () => {});
      running = true; camPill('ok', 'Camera ready');
      try { cams = await Html5Qrcode.getCameras(); } catch (_) { cams = []; }
      $('flipBtn').hidden = cams.length < 2;
      setupTorch();
      try { const t = currentTrack(); const caps = t && t.getCapabilities ? t.getCapabilities() : {}; if (caps.focusMode && caps.focusMode.includes('continuous')) t.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {}); } catch (_) {}
    } catch (err) {
      console.error('camera start failed', err);
      const name = (err && (err.name || err.message || String(err))) + '';
      camFail(/NotAllowed|Permission|denied/i.test(name) ? 'Camera permission denied. Allow camera access in the browser settings, then retry.' : /NotFound|Devices/i.test(name) ? 'No camera found on this device.' : /NotReadable|TrackStart|in use/i.test(name) ? 'Camera is in use by another app.' : 'Camera could not start.');
    } finally { starting = false; }
  }
  const currentTrack = () => { const v = document.querySelector('#reader video'); const s = v && v.srcObject; return s && s.getVideoTracks ? s.getVideoTracks()[0] : null; };
  function setupTorch() {
    const t = currentTrack(); let ok = false;
    try { ok = !!(t && t.getCapabilities && t.getCapabilities().torch); } catch (_) {}
    $('torchBtn').hidden = !ok; torchOn = false; $('torchBtn').setAttribute('aria-pressed', 'false');
  }
  $('torchBtn').addEventListener('click', async () => { const t = currentTrack(); if (!t) return; try { await t.applyConstraints({ advanced: [{ torch: !torchOn }] }); torchOn = !torchOn; $('torchBtn').setAttribute('aria-pressed', torchOn ? 'true' : 'false'); } catch (_) { $('torchBtn').hidden = true; } });
  $('flipBtn').addEventListener('click', () => { if (cams.length < 2) return; camIdx = (camIdx + 1) % cams.length; startCamera(cams[camIdx].id); });
  $('camRetry').addEventListener('click', () => startCamera());

  // Recovery: tab hidden/shown (iOS stops the stream), stream ended, or a frozen camera.
  const trackLive = () => { const t = currentTrack(); return !!t && t.readyState === 'live'; };
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { ping(); if (!trackLive() && !starting) startCamera(); } });
  window.addEventListener('pageshow', (e) => { if (e.persisted) { ping(); if (!trackLive()) startCamera(); } });
  setInterval(() => { if (!document.hidden && !starting && !$('camFail').classList.contains('show') && !trackLive()) startCamera(); }, 5000);

  // ───────── boot ─────────
  paintNet('warn', 'Checking…'); ping();
  startCamera();
  if (cfg.initial && cfg.initialCode) showOutcome(cfg.initial, cfg.initialCode, 200);
})();
</script>
</body>
</html>
