@extends('front.layout.master')

@section('content')

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

:root {
    --bg:        #060608;
    --surface:   #0e0e12;
    --surface2:  #16161d;
    --border:    rgba(255,255,255,0.07);
    --gold:      #f5b800;
    --gold-dim:  #c99300;
    --text:      #e8e8ef;
    --muted:     #6b6b7e;
    --red:       #e8445a;
    --radius:    14px;
    --font-head: 'Syne', sans-serif;
    --font-body: 'DM Sans', sans-serif;
}

/* ── Page base ── */
.ev-page {
    background: var(--bg);
    min-height: 100vh;
    font-family: var(--font-body);
    color: var(--text);
    padding-bottom: 80px;
}

.sub-banner {
    min-height: clamp(220px, 34vw, 360px);
    display: flex;
    align-items: center;
    background-size: cover;
    background-position: center;
}

/* ── Grid ── */
.ev-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
    padding-top: 48px;
}

/* ── Event Card ── */
.ev-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: border-color 0.25s, transform 0.25s, box-shadow 0.25s;
    text-decoration: none;
    color: inherit;
}
.ev-card:hover {
    border-color: rgba(245,184,0,0.3);
    transform: translateY(-4px);
    box-shadow: 0 20px 48px rgba(0,0,0,0.45), 0 0 0 1px rgba(245,184,0,0.08);
    text-decoration: none;
    color: inherit;
}

/* Cover image */
.ev-card-img {
    position: relative;
    height: clamp(210px, 26vw, 300px);
    overflow: hidden;
    background: var(--surface2);
}
.ev-card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.45s ease;
    display: block;
}
.ev-card:hover .ev-card-img img { transform: scale(1.05); }

/* Date badge */
.ev-card-date-badge {
    position: absolute;
    top: 14px; left: 14px;
    background: rgba(6,6,8,0.85);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(245,184,0,0.25);
    border-radius: 8px;
    padding: 8px 12px;
    text-align: center;
    min-width: 48px;
}
.ev-card-date-badge .day {
    font-family: var(--font-head);
    font-size: 22px; font-weight: 800;
    color: var(--gold);
    line-height: 1;
    display: block;
}
.ev-card-date-badge .mon {
    font-family: var(--font-head);
    font-size: 10px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--muted);
    display: block;
    margin-top: 2px;
}

/* Overlay gradient */
.ev-card-img::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 50%;
    background: linear-gradient(to top, rgba(14,14,18,0.9) 0%, transparent 100%);
    pointer-events: none;
}

/* Body */
.ev-card-body {
    padding: 20px 22px 22px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.ev-card-title {
    font-family: var(--font-head);
    font-size: 17px; font-weight: 800;
    color: #fff;
    margin: 0 0 14px;
    letter-spacing: -0.2px;
    line-height: 1.3;
    transition: color 0.2s;
}
.ev-card:hover .ev-card-title { color: var(--gold); }

/* Meta pills */
.ev-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
}
.ev-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 99px;
    padding: 5px 11px;
    font-size: 11px;
    color: var(--muted);
    white-space: nowrap;
}
.ev-meta-pill svg { width: 11px; height: 11px; opacity: 0.6; flex-shrink: 0; }

/* Buy button */
.ev-card-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    background: var(--gold);
    color: #000;
    font-family: var(--font-head);
    font-size: 12px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    border-radius: 8px;
    padding: 12px 20px;
    text-decoration: none;
    margin-top: auto;
    transition: background 0.2s, transform 0.15s;
}
.ev-card-btn:hover { background: #ffc820; color: #000; text-decoration: none; transform: scale(1.02); }
.ev-card-btn svg { width: 13px; height: 13px; }

/* ── Empty state ── */
.ev-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 24px;
    border: 1px dashed rgba(255,255,255,0.08);
    border-radius: var(--radius);
    background: var(--surface);
}
.ev-empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
.ev-empty h3 { font-family: var(--font-head); font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 8px; }
.ev-empty p  { font-size: 14px; color: var(--muted); margin: 0 0 24px; }
.ev-empty-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: #000;
    font-family: var(--font-head); font-size: 12px; font-weight: 800;
    letter-spacing: 1px; text-transform: uppercase;
    border-radius: 8px; padding: 12px 24px;
    text-decoration: none; transition: background 0.2s;
}
.ev-empty-btn:hover { background: #ffc820; color: #000; text-decoration: none; }

/* ── Pagination ── */
.ev-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding-top: 48px;
    flex-wrap: wrap;
}
.ev-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 38px; height: 38px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: var(--font-head);
    font-size: 12px; font-weight: 700;
    color: var(--muted);
    text-decoration: none;
    padding: 0 12px;
    transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.ev-page-btn:hover { border-color: rgba(245,184,0,0.4); color: var(--gold); text-decoration: none; }
.ev-page-btn.active { background: var(--gold); border-color: var(--gold); color: #000; }
.ev-page-btn.disabled { opacity: 0.3; pointer-events: none; }
</style>

<div class="sub-banner">
    <div class="container">
        <h6>Events</h6>
        <p>We are a techno events company focused on unforgettable nights and secure ticket booking.</p>
    </div>
</div>

<div class="kode_content_wrap ev-page">
    <section>
        <div class="container">
        <div class="ev-grid">

            @forelse($events as $event)
                <a class="ev-card" href="{{ route('front.events.show', $event) }}">

                    {{-- Cover --}}
                    <div class="ev-card-img">
                        <img src="{{ $event->cover_image_url ?? asset('extra-images/concert1.jpg') }}"
                             alt="{{ $event->name }}" loading="lazy">
                        {{-- Date badge --}}
                        <div class="ev-card-date-badge">
                            <span class="day">{{ $event->event_date->format('d') }}</span>
                            <span class="mon">{{ $event->event_date->format('M') }}</span>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="ev-card-body">
                        <div class="ev-card-title">{{ $event->name }}</div>

                        <div class="ev-card-meta">
                            {{-- Time --}}
                            <span class="ev-meta-pill">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}
                            </span>
                            {{-- Location --}}
                            <span class="ev-meta-pill">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                {{ $event->location }}
                            </span>
                            {{-- Year --}}
                            <span class="ev-meta-pill">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                {{ $event->event_date->format('Y') }}
                            </span>
                        </div>

                        <span class="ev-card-btn">
                            Buy Ticket
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                    </div>

                </a>
            @empty
                <div class="ev-empty">
                    <div class="ev-empty-icon">🎫</div>
                    <h3>No upcoming events</h3>
                    <p>Check back later for new event announcements.</p>
                    <a class="ev-empty-btn" href="{{ route('front.home') }}">← Back to Home</a>
                </div>
            @endforelse

        </div>

        {{-- Pagination --}}
        @if($events->hasPages())
            <div class="ev-pagination">
                {{-- Prev --}}
                @if($events->onFirstPage())
                    <span class="ev-page-btn disabled">← Prev</span>
                @else
                    <a class="ev-page-btn" href="{{ $events->previousPageUrl() }}">← Prev</a>
                @endif

                {{-- Pages --}}
                @foreach($events->getUrlRange(1, $events->lastPage()) as $page => $url)
                    <a class="ev-page-btn {{ $events->currentPage() === $page ? 'active' : '' }}"
                       href="{{ $url }}">{{ $page }}</a>
                @endforeach

                {{-- Next --}}
                @if($events->hasMorePages())
                    <a class="ev-page-btn" href="{{ $events->nextPageUrl() }}">Next →</a>
                @else
                    <span class="ev-page-btn disabled">Next →</span>
                @endif
            </div>
        @endif

        </div>
        </div>
    </section>
</div>

@endsection
