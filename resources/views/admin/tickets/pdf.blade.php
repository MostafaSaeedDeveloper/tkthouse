<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: DejaVu Sans, sans-serif;
    background: #000;
    color: #fff;
    width: 600px;
    margin: 0 auto;
}

/* ── HERO ── */
.hero {
    position: relative;
    width: 100%;
    height: 280px;
    overflow: hidden;
    background: #111;
}
.hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0.75;
}
.hero-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.7) 100%);
}
/* Red corner dots like in image */
.dot {
    position: absolute;
    width: 12px; height: 12px;
    border-radius: 50%;
    background: #cc2200;
}
.dot-tl { top: 14px; left: 14px; }
.dot-tc { top: 14px; left: 50%; margin-left: -6px; }
.dot-tr { top: 14px; right: 14px; }
.dot-ml { top: 50%; left: 14px; margin-top: -6px; }
.dot-mr { top: 50%; right: 14px; margin-top: -6px; }

.hero-sub {
    position: absolute;
    top: 18px; left: 0; right: 0;
    text-align: center;
    font-size: 11px;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
}
.hero-event-name {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    text-align: center;
    font-size: 72px;
    font-weight: 900;
    letter-spacing: 6px;
    color: rgba(255,255,255,0.18);
    text-transform: uppercase;
    line-height: 1;
    padding-bottom: 8px;
    /* strikethrough-like red line */
}
.hero-red-line {
    position: absolute;
    left: 0; right: 0;
    height: 3px;
    background: #cc2200;
    top: 60%;
}

/* ── EVENT TITLE BAR ── */
.title-bar {
    background: #0a0a0a;
    border-top: 1px solid #222;
    padding: 16px 20px;
    display: table;
    width: 100%;
}
.title-bar-left {
    display: table-cell;
    vertical-align: middle;
}
.title-bar-right {
    display: table-cell;
    text-align: right;
    vertical-align: middle;
    white-space: nowrap;
    color: rgba(255,255,255,0.6);
    font-size: 12px;
}
.event-title {
    font-size: 20px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #fff;
}

/* ── BODY ── */
.body {
    background: #0f0f0f;
    padding: 16px 20px 0;
}

/* ── INFO GRID ── */
.info-grid {
    display: table;
    width: 100%;
    margin-bottom: 12px;
}
.info-col {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding: 14px 16px;
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
}
.info-col + .info-col {
    border-left: none;
}
.info-section-title {
    font-size: 11px;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.45);
    margin-bottom: 10px;
    text-transform: uppercase;
}
.info-row {
    font-size: 12px;
    color: #ddd;
    margin-bottom: 5px;
    line-height: 1.4;
}
.info-row strong {
    color: rgba(255,255,255,0.5);
    font-weight: normal;
}

/* Second row of boxes */
.info-grid-2 {
    display: table;
    width: 100%;
    margin-bottom: 12px;
}
.info-col-2 {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding: 14px 16px;
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
}
.info-col-2 + .info-col-2 {
    border-left: none;
}

/* QR block */
.qr-block {
    text-align: center;
    padding: 10px;
}
.qr-label-top {
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    margin-bottom: 6px;
}
.qr-img {
    width: 110px;
    height: 110px;
    display: block;
    margin: 0 auto;
    background: #fff;
    padding: 4px;
}
.qr-label-bottom {
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
}

/* Extra info */
.extra-info {
    padding: 10px 16px 14px;
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    line-height: 1.7;
    border-top: 1px solid #2a2a2a;
}
.extra-info strong {
    color: rgba(255,255,255,0.5);
    font-weight: normal;
}

/* ── FOOTER ── */
.footer {
    background: #0a0a0a;
    border-top: 1px solid #1e1e1e;
    padding: 14px 20px;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    vertical-align: middle;
}
.footer-brand {
    font-size: 13px;
    font-weight: 700;
    color: #f5b800;
    letter-spacing: .5px;
}
.footer-tagline {
    font-size: 10px;
    color: rgba(255,255,255,0.35);
    margin-top: 2px;
}
.footer-right {
    display: table-cell;
    text-align: right;
    vertical-align: middle;
    font-size: 10px;
    color: rgba(255,255,255,0.3);
}
</style>
</head>
<body>

<!-- HERO -->
<div class="hero">
    @if($ticket->order?->event?->cover_image_url)
        <img class="hero-img" src="{{ $ticket->order->event->cover_image_url }}" alt="">
    @else
        <div style="width:100%;height:100%;background:linear-gradient(135deg,#111 0%,#1e1a00 100%);"></div>
    @endif
    <div class="hero-overlay"></div>

    <!-- dots -->
    <div class="dot dot-tl"></div>
    <div class="dot dot-tc"></div>
    <div class="dot dot-tr"></div>
    <div class="dot dot-ml"></div>
    <div class="dot dot-mr"></div>

    <div class="hero-sub">{{ strtoupper($ticket->order?->event?->organizer ?? 'TKTHouse') }}</div>

    <div class="hero-event-name">
        {{ strtoupper(substr($ticket->order?->event?->name ?? 'Event', 0, 10)) }}
        <div class="hero-red-line"></div>
    </div>
</div>

<!-- TITLE BAR -->
<div class="title-bar">
    <div class="title-bar-left">
        <div class="event-title">{{ strtoupper($ticket->order?->event?->name ?? 'Event') }}</div>
    </div>
    <div class="title-bar-right">
        @if($ticket->order?->event?->event_date)
            {{ strtoupper($ticket->order->event->event_date->format('d F Y')) }}
            at {{ \Carbon\Carbon::parse($ticket->order->event->event_time)->format('g:i A') }}
        @endif
    </div>
</div>

<!-- BODY -->
<div class="body">

    <!-- Row 1: Ticket Info + Ownership -->
    <div class="info-grid">
        <div class="info-col">
            <div class="info-section-title">Ticket Information</div>
            <div class="info-row">Name: {{ $ticket->name ?? $ticket->ticket_type ?? '-' }}</div>
            <div class="info-row">Price: {{ number_format($ticket->ticket_price, 0) }} EGP</div>
            <div class="info-row">Status: {{ str($ticket->status)->replace('_',' ')->title() }}</div>
            <div class="info-row">#: {{ $ticket->ticket_number }}</div>
        </div>
        <div class="info-col">
            <div class="info-section-title">Ownership Details</div>
            <div class="info-row">Name: {{ $ticket->holder_name ?: '-' }}</div>
            <div class="info-row">Email: {{ $ticket->holder_email ?: '-' }}</div>
            @if($ticket->holder_phone)
                <div class="info-row">Phone: {{ $ticket->holder_phone }}</div>
            @endif
        </div>
    </div>

    <!-- Row 2: Event Info + Venue + QR -->
    <div class="info-grid-2">
        <div class="info-col-2" style="width:33%;">
            <div class="info-section-title">Event Information</div>
            <div class="info-row">Name: {{ $ticket->order?->event?->name ?? '-' }}</div>
            <div class="info-row">Date &amp; Time: {{ $ticket->order?->event?->event_date?->format('d M Y') ?? '-' }}</div>
            @if($ticket->order?->event?->event_time)
                <div class="info-row">{{ \Carbon\Carbon::parse($ticket->order->event->event_time)->format('g:i A') }}</div>
            @endif

            <div class="info-section-title" style="margin-top:12px;">Venue Information</div>
            <div class="info-row">Name: {{ $ticket->order?->event?->venue ?? $ticket->order?->event?->location ?? '-' }}</div>
            <div class="info-row">Address: {{ $ticket->order?->event?->location ?? '-' }}</div>
            @if($ticket->order?->event?->map_url)
                <div class="info-row">Venue Location: <u>Get Directions</u></div>
            @endif
        </div>

        <div class="info-col-2" style="width:33%; border-left:none; text-align:center;">
            <div class="qr-block">
                <div class="qr-label-top">{{ strtoupper($ticket->name ?? $ticket->ticket_type ?? 'General') }}</div>
                <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Code">
                <div class="qr-label-bottom">{{ strtoupper(str($ticket->status)->replace('_',' ')) }}</div>
            </div>
        </div>
    </div>

    <!-- Extra attendee info if available -->
    @php
        $extras = collect([
            'Full Name'    => $ticket->holder_name,
            'Email'        => $ticket->holder_email,
            'Phone Number' => $ticket->holder_phone,
            'Birthdate'    => $ticket->holder_birthdate,
            'Gender'       => $ticket->holder_gender,
        ])->filter();
    @endphp
    @if($extras->count())
        <div class="extra-info">
            <strong>Extra Information</strong><br>
            @foreach($extras as $label => $val)
                {{ $label }}: {{ $val }}<br>
            @endforeach
        </div>
    @endif

</div>

<!-- FOOTER -->
<div class="footer">
    <div class="footer-left">
        <div class="footer-brand">tkthouse</div>
        <div class="footer-tagline">your access. your moment.</div>
    </div>
    <div class="footer-right">
        Generated on: {{ now()->format('Y-m-d H:i:s') }}
    </div>
</div>

</body>
</html>
