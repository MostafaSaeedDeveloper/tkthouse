<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 0; size: 760px auto; }
html, body { margin: 0; padding: 0; background: transparent; }
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
    background: transparent;
    color: #fff;
    width: 760px;
    margin: 0;
    padding: 0;
}

.ticket-shell {
    width: 760px;
    border-radius: 0;
    overflow: hidden;
    background: #0a0a0a;
    box-shadow: none;
    page-break-inside: avoid;
}

/* ── HERO ── */
.hero {
    position: relative;
    width: 760px;
    height: 280px;
    overflow: hidden;
    background: #0f0f0f;
}
.hero-img {
    width: 760px;
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
.dot-ml { top: 130px; left: 58px; }
.dot-mr { top: 130px; right: 58px; }



.body {
    background: #0f0f0f;
    padding: 10px;
}
.info-grid,
.info-grid-2 {
    display: table;
    width: 760px;
    border-spacing: 6px;
    margin: 0;
}
.info-col,
.info-col-2 {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    background: #171717;
    border-radius: 16px;
    padding: 10px 12px;
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
    width: 108px;
    height: 108px;
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
    padding: 10px 14px 12px;
    display: table;
    width: 760px;
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
        $heroImageSource = $event?->cover_image ?: $event?->images?->first()?->path;
        $heroImage = null;

        if ($heroImageSource) {
            if (\Illuminate\Support\Str::startsWith($heroImageSource, ['http://', 'https://', 'data:'])) {
                $heroImage = $heroImageSource;
            } elseif (\Illuminate\Support\Str::startsWith($heroImageSource, 'uploads/')) {
                $heroImage = public_path($heroImageSource);
            } elseif (\Illuminate\Support\Str::startsWith($heroImageSource, 'storage/')) {
                $heroImage = public_path($heroImageSource);
            } else {
                $heroImage = public_path('storage/'.ltrim($heroImageSource, '/'));
            }
        }
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
            <div class="info-row">Venue Location: @if($event?->map_url)<a class="info-link" href="{{ $event->map_url }}">Get Directions</a>@else<span class="info-link">Get Directions</span>@endif</div>
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
                    'Gender'                 => $ticket->holder_gender ?? $ticket->orderItem?->holder_gender,
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
