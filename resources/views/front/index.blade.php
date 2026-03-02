@extends('front.layout.master')

@section('content')



            <div class="banner_slider">
                @forelse($featuredEvents as $event)
                    <div class="slide left-align">
                        <img src="{{ $event->cover_image_url ?? asset('extra-images/kf_slide_img14.jpg') }}" alt="{{ $event->name }}">
                        <div class="banner_content container">
                            <div class="b_title animated">{{ strtoupper($event->name) }}</div>
                            <p class="animated">{{ $event->event_date->format('F d, Y') }} - {{ \\Carbon\\Carbon::parse($event->event_time)->format('g:i A') }} · {{ $event->location }}</p>
                            <a href="{{ route('front.events.show', $event) }}" class="btn_normal border_btn animated">BOOK NOW</a>
                            <a href="{{ route('front.events') }}" class="btn_normal border_btn animated ">EXPLORE EVENTS</a>
                        </div>
                    </div>
                @empty
                    <div class="slide left-align">
                        <img src="extra-images/kf_slide_img14.jpg" alt="banner img">
                        <div class="banner_content container">
                            <div class="b_title animated">TKTHOUSE TECH EVENTS ARE LIVE!</div>
                            <p class="animated">We organize technology events and provide easy ticket booking for every attendee.</p>
                            <a href="{{ route('front.events') }}" class="btn_normal border_btn animated">BOOK NOW</a>
                            <a href="{{ route('front.events') }}" class="btn_normal border_btn animated ">EXPLORE EVENTS</a>
                        </div>
                    </div>
                @endforelse
            </div>


            <!--Main Content Wrap Start-->
            <style>
            @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

            :root {
                --ev-bg:       #060608;
                --ev-surface:  #0e0e12;
                --ev-surface2: #16161d;
                --ev-border:   rgba(255,255,255,0.07);
                --ev-gold:     #f5b800;
                --ev-text:     #e8e8ef;
                --ev-muted:    #6b6b7e;
                --ev-radius:   14px;
                --ev-font-h:   'Syne', sans-serif;
                --ev-font-b:   'DM Sans', sans-serif;
            }

            /* ── Section wrapper ── */
            .ev-home-section {
                background: var(--ev-bg);
                padding: 64px 0 80px;
                font-family: var(--ev-font-b);
            }

            /* ── Section header ── */
            .ev-home-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 36px;
                flex-wrap: wrap;
            }
            .ev-home-eyebrow {
                font-family: var(--ev-font-h);
                font-size: 10px; font-weight: 700;
                letter-spacing: 3.5px; text-transform: uppercase;
                color: var(--ev-gold);
                display: flex; align-items: center; gap: 10px;
                margin-bottom: 8px;
            }
            .ev-home-eyebrow::before {
                content: '';
                width: 24px; height: 2px;
                background: var(--ev-gold); border-radius: 2px;
            }
            .ev-home-title {
                font-family: var(--ev-font-h);
                font-size: clamp(22px, 3.5vw, 34px);
                font-weight: 800;
                color: #fff;
                margin: 0;
                letter-spacing: -0.5px;
                line-height: 1.1;
            }
            .ev-home-title em { color: var(--ev-gold); font-style: normal; }
            .ev-see-all {
                display: inline-flex; align-items: center; gap: 7px;
                font-family: var(--ev-font-h);
                font-size: 11px; font-weight: 700;
                letter-spacing: 1.5px; text-transform: uppercase;
                color: var(--ev-muted);
                text-decoration: none;
                border: 1px solid var(--ev-border);
                border-radius: 8px;
                padding: 10px 18px;
                transition: color 0.2s, border-color 0.2s;
                white-space: nowrap;
            }
            .ev-see-all:hover { color: var(--ev-gold); border-color: rgba(245,184,0,0.35); text-decoration: none; }
            .ev-see-all svg { width: 12px; height: 12px; }

            /* ── Grid ── */
            .ev-home-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }

            /* ── Card ── */
            .ev-home-card {
                background: var(--ev-surface);
                border: 1px solid var(--ev-border);
                border-radius: var(--ev-radius);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                text-decoration: none;
                color: var(--ev-text);
                transition: border-color 0.25s, transform 0.25s, box-shadow 0.25s;
            }
            .ev-home-card:hover {
                border-color: rgba(245,184,0,0.3);
                transform: translateY(-4px);
                box-shadow: 0 20px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(245,184,0,0.08);
                text-decoration: none;
                color: var(--ev-text);
            }

            /* Image */
            .ev-home-card-img {
                position: relative;
                aspect-ratio: 16/9;
                overflow: hidden;
                background: var(--ev-surface2);
            }
            .ev-home-card-img img {
                width: 100%; height: 100%;
                object-fit: cover;
                display: block;
                transition: transform 0.45s ease;
            }
            .ev-home-card:hover .ev-home-card-img img { transform: scale(1.06); }
            .ev-home-card-img::after {
                content: '';
                position: absolute; bottom: 0; left: 0; right: 0;
                height: 55%;
                background: linear-gradient(to top, rgba(14,14,18,0.9) 0%, transparent 100%);
                pointer-events: none;
            }

            /* Date badge */
            .ev-home-date-badge {
                position: absolute;
                top: 12px; left: 12px;
                background: rgba(6,6,8,0.85);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(245,184,0,0.25);
                border-radius: 8px;
                padding: 7px 11px;
                text-align: center;
                min-width: 44px;
                z-index: 2;
            }
            .ev-home-date-badge .day {
                font-family: var(--ev-font-h);
                font-size: 20px; font-weight: 800;
                color: var(--ev-gold); line-height: 1; display: block;
            }
            .ev-home-date-badge .mon {
                font-family: var(--ev-font-h);
                font-size: 9px; font-weight: 700;
                letter-spacing: 1.5px; text-transform: uppercase;
                color: var(--ev-muted); display: block; margin-top: 2px;
            }

            /* Status pill */
            .ev-home-status {
                position: absolute;
                top: 12px; right: 12px;
                background: rgba(6,6,8,0.85);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 99px;
                padding: 4px 10px;
                font-family: var(--ev-font-h);
                font-size: 9px; font-weight: 700;
                letter-spacing: 1px; text-transform: uppercase;
                color: var(--ev-muted);
                z-index: 2;
            }

            /* Body */
            .ev-home-card-body {
                padding: 18px 20px 20px;
                display: flex; flex-direction: column; flex: 1;
            }
            .ev-home-card-title {
                font-family: var(--ev-font-h);
                font-size: 15px; font-weight: 800;
                color: #fff; margin: 0 0 12px;
                letter-spacing: -0.2px; line-height: 1.3;
                transition: color 0.2s;
            }
            .ev-home-card:hover .ev-home-card-title { color: var(--ev-gold); }

            /* Meta */
            .ev-home-meta {
                display: flex; flex-wrap: wrap; gap: 7px;
                margin-bottom: 16px;
            }
            .ev-home-pill {
                display: inline-flex; align-items: center; gap: 5px;
                background: var(--ev-surface2);
                border: 1px solid var(--ev-border);
                border-radius: 99px;
                padding: 4px 10px;
                font-size: 11px; color: var(--ev-muted);
                white-space: nowrap;
            }
            .ev-home-pill svg { width: 10px; height: 10px; opacity: 0.6; flex-shrink: 0; }

            /* Buy btn */
            .ev-home-card-btn {
                display: flex; align-items: center; justify-content: center; gap: 7px;
                background: var(--ev-gold); color: #000;
                font-family: var(--ev-font-h);
                font-size: 11px; font-weight: 800;
                letter-spacing: 1px; text-transform: uppercase;
                border-radius: 8px; padding: 11px 18px;
                text-decoration: none; margin-top: auto;
                transition: background 0.2s, transform 0.15s;
            }
            .ev-home-card-btn:hover { background: #ffc820; color: #000; text-decoration: none; transform: scale(1.02); }
            .ev-home-card-btn svg { width: 12px; height: 12px; }

            /* Empty */
            .ev-home-empty {
                grid-column: 1 / -1;
                text-align: center;
                padding: 60px 24px;
                border: 1px dashed rgba(255,255,255,0.08);
                border-radius: var(--ev-radius);
                background: var(--ev-surface);
            }
            .ev-home-empty-icon { font-size: 40px; margin-bottom: 14px; opacity: 0.4; }
            .ev-home-empty h3 { font-family: var(--ev-font-h); font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 6px; }
            .ev-home-empty p  { font-size: 13px; color: var(--ev-muted); margin: 0 0 20px; }
            .ev-home-empty-btn {
                display: inline-flex; align-items: center; gap: 8px;
                background: var(--ev-gold); color: #000;
                font-family: var(--ev-font-h); font-size: 11px; font-weight: 800;
                letter-spacing: 1px; text-transform: uppercase;
                border-radius: 8px; padding: 11px 22px;
                text-decoration: none; transition: background 0.2s;
            }
            .ev-home-empty-btn:hover { background: #ffc820; color: #000; text-decoration: none; }
            </style>

            <section class="ev-home-section">
                <div class="container">

                    {{-- Section header --}}
                    <div class="ev-home-header">
                        <div>
                            <div class="ev-home-eyebrow">TKTHouse</div>
                            <h2 class="ev-home-title">Upcoming <em>Events</em></h2>
                        </div>
                        <a href="{{ route('front.events') }}" class="ev-see-all">
                            View All
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>

                    {{-- Cards grid --}}
                    <div class="ev-home-grid">
                        @forelse($upcomingEvents as $event)
                            <a class="ev-home-card" href="{{ route('front.events.show', $event) }}">

                                <div class="ev-home-card-img">
                                    <img src="{{ $event->cover_image_url ?? asset('extra-images/black-img/event-list6.jpg') }}"
                                         alt="{{ $event->name }}" loading="lazy">
                                    <div class="ev-home-date-badge">
                                        <span class="day">{{ $event->event_date->format('d') }}</span>
                                        <span class="mon">{{ $event->event_date->format('M') }}</span>
                                    </div>
                                    <div class="ev-home-status">
                                        {{ str($event->status)->replace('_', ' ')->title() }}
                                    </div>
                                </div>

                                <div class="ev-home-card-body">
                                    <div class="ev-home-card-title">{{ $event->name }}</div>
                                    <div class="ev-home-meta">
                                        <span class="ev-home-pill">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}
                                        </span>
                                        <span class="ev-home-pill">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            {{ $event->location }}
                                        </span>
                                    </div>
                                    <span class="ev-home-card-btn">
                                        Buy Tickets
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </span>
                                </div>

                            </a>
                        @empty
                            <div class="ev-home-empty">
                                <div class="ev-home-empty-icon">🎫</div>
                                <h3>No upcoming events yet</h3>
                                <p>Stay tuned — new dates coming soon.</p>
                                <a class="ev-home-empty-btn" href="{{ route('front.events') }}">Explore Events →</a>
                            </div>
                        @endforelse
                    </div>

                </div>
            </section>

            <section class="ev-home-section">
                <div class="container">
                    <div class="ev-home-header">
                        <div>
                            <div class="ev-home-eyebrow">TKTHouse</div>
                            <h2 class="ev-home-title">Previous <em>Events</em></h2>
                        </div>
                        <a href="{{ route('front.events') }}" class="ev-see-all">
                            View All
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>

                    <div class="ev-home-grid">
                        @forelse($previousEvents as $event)
                            <a class="ev-home-card" href="{{ route('front.events.show', $event) }}">
                                <div class="ev-home-card-img">
                                    <img src="{{ $event->cover_image_url ?? asset('extra-images/black-img/event-list6.jpg') }}" alt="{{ $event->name }}" loading="lazy">
                                    <div class="ev-home-date-badge">
                                        <span class="day">{{ $event->event_date->format('d') }}</span>
                                        <span class="mon">{{ $event->event_date->format('M') }}</span>
                                    </div>
                                    <div class="ev-home-status">Finished</div>
                                </div>
                                <div class="ev-home-card-body">
                                    <div class="ev-home-card-title">{{ $event->name }}</div>
                                    <div class="ev-home-meta">
                                        <span class="ev-home-pill">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            {{ \\Carbon\\Carbon::parse($event->event_time)->format('g:i A') }}
                                        </span>
                                        <span class="ev-home-pill">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            {{ $event->location }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="ev-home-empty">
                                <div class="ev-home-empty-icon">🕒</div>
                                <h3>No previous events yet</h3>
                                <p>Your event history will appear here.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
            <!--Main Content Wrap End-->


@endsection
