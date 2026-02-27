<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#060608">
  <title>TKT Scanner</title>
  <link rel="shortcut icon" href="{{ asset('admin/assets/media/favicons/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #060608;
  --surface:  #0e0e12;
  --surface2: #16161d;
  --border:   rgba(255,255,255,0.07);
  --gold:     #f5b800;
  --gold-dim: rgba(245,184,0,0.12);
  --text:     #e8e8ef;
  --muted:    #5e5e72;
  --green:    #22c55e;
  --red:      #e8445a;
  --amber:    #f59e0b;
}

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

/* ── Page Shell ─────────────────────────────────────── */
.sc-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  max-width: 520px;
  margin: 0 auto;
  padding: 0 16px 40px;
}

/* ── Top Bar ────────────────────────────────────────── */
.sc-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 0 14px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 24px;
}
.sc-logo {
  font-family: 'Syne', sans-serif;
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.5px;
  text-decoration: none;
}
.sc-logo span { color: var(--gold); }
.sc-badge {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--gold);
  background: var(--gold-dim);
  border: 1px solid rgba(245,184,0,0.25);
  border-radius: 6px;
  padding: 3px 10px;
}

/* ── Camera Box ─────────────────────────────────────── */
.sc-camera-wrap {
  position: relative;
  border-radius: 20px;
  overflow: hidden;
  background: #000;
  border: 1px solid var(--border);
  margin-bottom: 20px;
  aspect-ratio: 1 / 1;
}
.sc-camera-wrap #reader {
  width: 100% !important;
  border: none !important;
}
.sc-camera-wrap #reader video {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover;
}
/* kill html5-qrcode default UI */
.sc-camera-wrap #reader img,
.sc-camera-wrap #reader button,
.sc-camera-wrap #reader select,
.sc-camera-wrap #reader #reader__dashboard,
.sc-camera-wrap #reader__dashboard_section_csr,
.sc-camera-wrap #reader__status_span { display: none !important; }

/* Scan overlay corners */
.sc-corners {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 10;
}
.sc-corner {
  position: absolute;
  width: 28px;
  height: 28px;
  border-color: var(--gold);
  border-style: solid;
  border-width: 0;
}
.sc-corner.tl { top: 18px; left: 18px; border-top-width: 3px; border-left-width: 3px; border-radius: 4px 0 0 0; }
.sc-corner.tr { top: 18px; right: 18px; border-top-width: 3px; border-right-width: 3px; border-radius: 0 4px 0 0; }
.sc-corner.bl { bottom: 18px; left: 18px; border-bottom-width: 3px; border-left-width: 3px; border-radius: 0 0 0 4px; }
.sc-corner.br { bottom: 18px; right: 18px; border-bottom-width: 3px; border-right-width: 3px; border-radius: 0 0 4px 0; }

/* Scan line animation */
.sc-scanline {
  position: absolute;
  left: 18px; right: 18px;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--gold), transparent);
  box-shadow: 0 0 10px var(--gold);
  animation: scanline 2s ease-in-out infinite;
  z-index: 11;
}
@keyframes scanline {
  0%   { top: 18px; opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: 1; }
  100% { top: calc(100% - 18px); opacity: 0; }
}

.sc-camera-hint {
  position: absolute;
  bottom: 14px;
  left: 0; right: 0;
  text-align: center;
  font-size: 11px;
  color: rgba(255,255,255,0.4);
  z-index: 12;
  pointer-events: none;
  letter-spacing: 0.5px;
}

/* ── OR Divider ─────────────────────────────────────── */
.sc-or {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  color: var(--muted);
  font-size: 11px;
  letter-spacing: 1px;
  text-transform: uppercase;
}
.sc-or::before, .sc-or::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* ── Manual Input ───────────────────────────────────── */
.sc-input-wrap { position: relative; margin-bottom: 14px; }
.sc-input {
  width: 100%;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  padding: 14px 50px 14px 18px;
  outline: none;
  transition: border-color .18s, box-shadow .18s;
}
.sc-input:focus {
  border-color: rgba(245,184,0,0.5);
  box-shadow: 0 0 0 3px rgba(245,184,0,0.08);
}
.sc-input::placeholder { color: var(--muted); }
.sc-input-icon {
  position: absolute;
  right: 16px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 16px;
  pointer-events: none;
}

.sc-submit {
  width: 100%;
  background: var(--gold);
  border: none;
  border-radius: 12px;
  color: #0d0d10;
  font-family: 'Syne', sans-serif;
  font-size: 15px;
  font-weight: 800;
  padding: 14px;
  cursor: pointer;
  letter-spacing: 0.3px;
  transition: all .18s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.sc-submit:hover { background: #ffc107; transform: translateY(-1px); }
.sc-submit:active { transform: translateY(0); }

/* ── Result Card ────────────────────────────────────── */
.sc-result {
  margin-top: 24px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  animation: slideUp .3s ease;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.sc-result-header {
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.sc-result-title {
  font-family: 'Syne', sans-serif;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--gold);
  display: flex;
  align-items: center;
  gap: 8px;
}
.sc-result-title::before {
  content: '';
  width: 3px; height: 13px;
  background: var(--gold);
  border-radius: 2px;
}

/* Status badges */
.sc-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: 'Syne', sans-serif;
  font-size: 11px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 99px;
}
.sc-status.checked_in     { color: var(--green); background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); }
.sc-status.not_checked_in { color: var(--amber); background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); }
.sc-status.canceled       { color: var(--red);   background: rgba(232,68,90,.12);  border: 1px solid rgba(232,68,90,.3); }

/* Holder row */
.sc-holder {
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  border-bottom: 1px solid var(--border);
}
.sc-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: var(--gold-dim);
  border: 2px solid rgba(245,184,0,0.25);
  color: var(--gold);
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sc-holder-name  { font-weight: 700; font-size: 16px; color: #fff; }
.sc-holder-email { font-size: 12px; color: var(--muted); margin-top: 2px; }
.sc-holder-phone { font-size: 12px; color: var(--muted); }

/* Info rows */
.sc-info { padding: 4px 20px 16px; }
.sc-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  font-size: 13px;
  gap: 12px;
}
.sc-info-row:last-child { border-bottom: none; }
.sc-info-label { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
.sc-info-val   { color: var(--text); font-weight: 500; text-align: right; }

/* Action buttons */
.sc-actions {
  padding: 16px 20px 20px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  border-top: 1px solid var(--border);
}
.sc-actions-full { grid-template-columns: 1fr; }
.sc-act-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 13px 12px;
  border-radius: 12px;
  font-family: 'Syne', sans-serif;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: all .18s;
  letter-spacing: 0.2px;
}
.sc-act-checkin  { background: rgba(34,197,94,.12);  color: var(--green); border: 1px solid rgba(34,197,94,.3); }
.sc-act-checkout { background: rgba(245,158,11,.1);  color: var(--amber); border: 1px solid rgba(245,158,11,.3); }
.sc-act-cancel   { background: rgba(232,68,90,.1);   color: var(--red);   border: 1px solid rgba(232,68,90,.3); }
.sc-act-view     { background: var(--surface2);      color: var(--text);  border: 1px solid var(--border); grid-column: span 2; }
.sc-act-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }

/* ── Empty / Loading state ──────────────────────────── */
.sc-idle {
  text-align: center;
  padding: 32px 20px;
  color: var(--muted);
  font-size: 13px;
}
.sc-idle i { font-size: 36px; color: rgba(245,184,0,0.2); display: block; margin-bottom: 12px; }
</style>
</head>

<body>
<div class="sc-shell">

  {{-- Top bar --}}
  <div class="sc-topbar">
    <a href="{{ route('admin.dashboard') }}" class="sc-logo"><img style="height: 30px" src="{{asset('images/logo-light.png')}}" alt=""><span>.</span></a>
    <span class="sc-badge"><i class="fa fa-qrcode" style="margin-right:5px;"></i>Scanner</span>
  </div>

  @include('admin.partials.flash')

  {{-- Camera --}}
  <div class="sc-camera-wrap">
    <div id="reader"></div>
    <div class="sc-corners">
      <div class="sc-corner tl"></div>
      <div class="sc-corner tr"></div>
      <div class="sc-corner bl"></div>
      <div class="sc-corner br"></div>
      <div class="sc-scanline"></div>
    </div>
    <div class="sc-camera-hint">Point camera at ticket QR code</div>
  </div>

  {{-- Manual entry --}}
  <div class="sc-or">or enter manually</div>

  <form method="POST" action="{{ route('admin.tickets.scanner.lookup') }}">
    @csrf
    <div class="sc-input-wrap">
      <input
        class="sc-input"
        type="text"
        id="scanner-code"
        name="code"
        value="{{ old('code', $lastCode ?? '') }}"
        placeholder="Ticket number or QR data…"
        autocomplete="off"
        autocorrect="off"
        spellcheck="false"
        required>
      <i class="fa fa-ticket sc-input-icon"></i>
    </div>
    <button class="sc-submit" type="submit">
      <i class="fa fa-magnifying-glass"></i> Look Up Ticket
    </button>
  </form>

  {{-- Result --}}
  @isset($ticket)
    @php
      $initials = collect(explode(' ', (string)($ticket->holder_name ?: 'NA')))
        ->filter()->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');
      $statusLabel = match($ticket->status) {
        'checked_in'     => 'Checked In',
        'not_checked_in' => 'Not Checked In',
        'canceled'       => 'Canceled',
        default          => str($ticket->status)->replace('_',' ')->title(),
      };
    @endphp

    <div class="sc-result">

      <div class="sc-result-header">
        <div class="sc-result-title">Ticket Found</div>
        <span class="sc-status {{ $ticket->status }}">
          @if($ticket->status === 'checked_in')     <i class="fa fa-circle-check"></i>
          @elseif($ticket->status === 'canceled')   <i class="fa fa-circle-xmark"></i>
          @else                                      <i class="fa fa-clock"></i>
          @endif
          {{ $statusLabel }}
        </span>
      </div>

      <div class="sc-holder">
        <div class="sc-avatar">{{ $initials ?: 'NA' }}</div>
        <div>
          <div class="sc-holder-name">{{ $ticket->holder_name ?: 'Unknown' }}</div>
          <div class="sc-holder-email">{{ $ticket->holder_email ?: '-' }}</div>
          @if($ticket->holder_phone)
            <div class="sc-holder-phone"><i class="fa fa-phone" style="font-size:10px;margin-right:4px;"></i>{{ $ticket->holder_phone }}</div>
          @endif
        </div>
      </div>

      <div class="sc-info">
        <div class="sc-info-row">
          <span class="sc-info-label">Ticket #</span>
          <span class="sc-info-val" style="font-family:monospace;font-size:12px;">{{ $ticket->ticket_number }}</span>
        </div>
        <div class="sc-info-row">
          <span class="sc-info-label">Event</span>
          <span class="sc-info-val">{{ $ticket->eventLabel() ?: '-' }}</span>
        </div>
        <div class="sc-info-row">
          <span class="sc-info-label">Ticket Type</span>
          <span class="sc-info-val">{{ $ticket->ticketTypeLabel() ?: '-' }}</span>
        </div>
        <div class="sc-info-row">
          <span class="sc-info-label">Order #</span>
          <span class="sc-info-val">{{ $ticket->order?->order_number ?? '-' }}</span>
        </div>
        @if($ticket->checked_in_at)
          <div class="sc-info-row">
            <span class="sc-info-label">Checked In At</span>
            <span class="sc-info-val" style="color:var(--green);">{{ $ticket->checked_in_at->format('d M, H:i') }}</span>
          </div>
        @endif
      </div>

      <form method="POST" action="{{ route('admin.tickets.scanner.status', $ticket) }}">
        @csrf
        <div class="sc-actions">
          <button name="status" value="checked_in"     class="sc-act-btn sc-act-checkin"  type="submit"><i class="fa fa-circle-check"></i>  Check In</button>
          <button name="status" value="not_checked_in" class="sc-act-btn sc-act-checkout" type="submit"><i class="fa fa-rotate-left"></i>     Check Out</button>
          <button name="status" value="canceled"       class="sc-act-btn sc-act-cancel"   type="submit"><i class="fa fa-ban"></i>             Cancel</button>
          <a href="{{ route('admin.tickets.show', $ticket) }}" class="sc-act-btn sc-act-view"><i class="fa fa-arrow-up-right-from-square"></i> Open Full Ticket</a>
        </div>
      </form>

    </div>
  @else
    <div class="sc-idle">
      <i class="fa fa-qrcode"></i>
      Scan a QR code or enter a ticket number above
    </div>
  @endisset

</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(() => {
  const input = document.getElementById('scanner-code');
  const readerEl = document.getElementById('reader');
  if (!window.Html5Qrcode || !readerEl) return;

  const qr = new Html5Qrcode('reader');

  qr.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 220, height: 220 } },
    (decoded) => {
      input.value = decoded;
      // auto-submit on scan
      input.closest('form') ? input.closest('form').submit() : null;
    },
    () => {}
  ).catch(() => {
    // camera not available — hide the camera box gracefully
    readerEl.closest('.sc-camera-wrap').style.display = 'none';
  });
})();
</script>
</body>
</html>
