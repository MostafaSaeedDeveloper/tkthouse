<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 0; }
html, body { margin: 0; padding: 0; width: 100%; background: #000; }
@font-face {
    font-family: 'Glancyr';
    src: url('{{ public_path('fonts/Glancyr-Regular.otf') }}') format('opentype');
    font-weight: 400;
    font-style: normal;
}
@font-face {
    font-family: 'Glancyr';
    src: url('{{ public_path('fonts/Glancyr-Medium.otf') }}') format('opentype');
    font-weight: 500;
    font-style: normal;
}
@font-face {
    font-family: 'Glancyr';
    src: url('{{ public_path('fonts/Glancyr-Bold.otf') }}') format('opentype');
    font-weight: 700;
    font-style: normal;
}
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Glancyr', DejaVu Sans, sans-serif;
    background: #000;
    color: #fff;
    width: 100%;
    margin: 0;
    padding: 0;
}

.ticket-shell {
    border-radius: 0;
    overflow: hidden;
    background: #0a0a0a;
    box-shadow: none;
}

/* ── HERO ── */
.hero {
    position: relative;
    width: 100%;
    height: 390px;
    overflow: hidden;
    background: #0f0f0f;
}
.hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0.58;
}
.hero-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.92) 86%);
}
.dot {
    position: absolute;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #8f1200;
}
.dot-tl { top: 14px; left: 64px; }
.dot-tc { top: 6px; left: 50%; margin-left: -7px; }
.dot-tr { top: 8px; right: 62px; }
.dot-tm { top: 72px; left: 50%; margin-left: -7px; }
.dot-ml { top: 186px; left: 58px; }
.dot-mr { top: 186px; right: 58px; }

.hero-sub {
    position: absolute;
    top: 34px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.33);
}
.hero-event-name {
    position: absolute;
    left: 0;
    right: 0;
    top: 164px;
    text-align: center;
    font-size: 74px;
    font-weight: 900;
    letter-spacing: 4px;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    line-height: 1;
}
.hero-red-line {
    position: absolute;
    left: 70px;
    right: 70px;
    height: 3px;
    background: #8f1200;
    top: 226px;
}

.title-bar {
    background: linear-gradient(to bottom, rgba(22,22,22,0.92), #121212);
    padding: 22px 34px;
    display: table;
    width: 100%;
}
.title-bar-left, .title-bar-right {
    display: table-cell;
    vertical-align: middle;
}
.title-bar-right {
    text-align: right;
    color: rgba(255,255,255,0.83);
    font-size: 13px;
}
.event-title {
    font-size: 45px;
    font-weight: 900;
    letter-spacing: .4px;
    text-transform: uppercase;
    color: #fff;
}

.body {
    background: #0f0f0f;
    padding: 14px;
}
.info-grid,
.info-grid-2 {
    display: table;
    width: 100%;
    border-spacing: 8px;
    margin: 0;
}
.info-col,
.info-col-2 {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    background: #171717;
    border-radius: 16px;
    padding: 14px 16px;
}
.info-section-title {
    font-size: 12px;
    color: #fff;
    margin-bottom: 8px;
    font-weight: 700;
}
.info-row {
    font-size: 12px;
    color: rgba(255,255,255,0.95);
    margin-bottom: 5px;
    line-height: 1.3;
}
.info-link {
    color: #fff;
    text-decoration: underline;
}

.qr-block {
    text-align: center;
}
.qr-label-top,
.qr-label-bottom {
    font-size: 12px;
    color: #fff;
    text-transform: uppercase;
    margin: 0 0 10px;
}
.qr-img {
    width: 126px;
    height: 126px;
    display: block;
    margin: 0 auto 10px;
    background: #fff;
    padding: 4px;
}
.extra-info {
    margin-top: 8px;
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    line-height: 1.33;
    text-align: left;
}
.extra-info strong {
    color: #fff;
    font-weight: normal;
}

.footer {
    background: #101010;
    border-top: 1px solid #212121;
    padding: 14px 22px 16px;
    display: table;
    width: 100%;
}
.footer-left,
.footer-right {
    display: table-cell;
    vertical-align: middle;
}
.footer-right {
    text-align: right;
    font-size: 10px;
    color: rgba(255,255,255,0.62);
}
.footer-brand {
    font-size: 13px;
    font-weight: 700;
    color: #f0b20f;
}

</style>
</head>
<body>
<div class="ticket-shell">

<!-- HERO -->
<div class="hero">
    @php
        $heroImage = $event?->cover_image_url ?: $event?->images?->first()?->path_url;
    @endphp
    @if($heroImage)
        <img class="hero-img" src="{{ $heroImage }}" alt="">
    @else
        <div style="width:100%;height:100%;background:linear-gradient(135deg,#111 0%,#1e1a00 100%);"></div>
    @endif
    <div class="hero-overlay"></div>

    <!-- dots -->
    <div class="dot dot-tl"></div>
    <div class="dot dot-tc"></div>
    <div class="dot dot-tr"></div>
    <div class="dot dot-tm"></div>
    <div class="dot dot-ml"></div>
    <div class="dot dot-mr"></div>

    <div class="hero-sub">{{ strtoupper($event?->organizer ?? 'TKTHOUSE') }}</div>

    <div class="hero-event-name">
        {{ strtoupper($event?->name ?? 'Event') }}
        <div class="hero-red-line"></div>
    </div>
</div>

<!-- TITLE BAR -->
<div class="title-bar">
    <div class="title-bar-left">
        <div class="event-title">{{ strtoupper($event?->name ?? 'Event') }}</div>
    </div>
    <div class="title-bar-right">
        @if($event?->event_date)
            {{ strtoupper($event->event_date->format('d M Y')) }} at {{ $event?->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : '-' }}
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
        <div class="info-col-2">
            <div class="info-section-title">Event Information</div>
            <div class="info-row">Name: {{ $event?->name ?? '-' }}</div>
            <div class="info-row">Date and Time: @if($event?->event_date){{ $event->event_date->format('d M Y') }} @endif {{ $event?->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g a') : '-' }}</div>

            <div class="info-section-title" style="margin-top:12px;">Venue Information</div>
            <div class="info-row">Name: {{ $event?->venue ?? $event?->location ?? '-' }}</div>
            <div class="info-row">Address: {{ $event?->location ?? '-' }}</div>
            <div class="info-row">Venue Location: <span class="info-link">Get Directions</span></div>
        </div>

        <div class="info-col-2">
            <div class="qr-block">
                <div class="qr-label-top">{{ strtoupper($ticket->name ?? $ticket->ticket_type ?? 'General') }}</div>
                <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Code">
                <div class="qr-label-bottom">{{ strtoupper(str($ticket->status)->replace('_',' ')) }}</div>
            </div>

            <!-- Extra attendee info if available -->
            @php
                $extras = collect([
                    'Social Media Account URL' => $ticket->holder_social_link,
                    'Email'                  => $ticket->holder_email,
                    'Full Name'              => $ticket->holder_name,
                    'Birthdate'              => $ticket->holder_birthdate,
                    'Phone Number'           => $ticket->holder_phone,
                    'Gender'                 => $ticket->holder_gender,
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
    </div>

</div>

<!-- FOOTER -->
<div class="footer">
    <div class="footer-left">
        <div class="footer-brand">tkthouse · Your Pass to the Pulse</div>
    </div>
    <div class="footer-right">
        Generated on: {{ now()->format('Y-m-d H:i:s') }}
    </div>
</div>
</div>

</body>
</html>
