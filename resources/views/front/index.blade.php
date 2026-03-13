@extends('front.layout.master')

@section('content')



            <div class="banner_slider">
                @forelse($featuredEvents as $event)
                    @php
                        $homeBannerImage = $event->event_banner_url ?: ($event->cover_image_url ?: asset('extra-images/kf_slide_img14.jpg'));
                    @endphp
                    <div class="slide left-align">
                        <a class="banner-slide-link" href="{{ route('front.events.show', $event) }}" aria-label="{{ $event->name }}">
                            <img src="{{ $homeBannerImage }}" alt="{{ $event->name }}">
                        </a>
                    </div>
                @empty
                    <div class="slide left-align">
                        <img src="extra-images/kf_slide_img14.jpg" alt="banner img">
                    </div>
                @endforelse
            </div>

            <div class="ev-home-search-wrap">
                <div class="container">
                    <form class="ev-home-search" method="GET" action="{{ route('front.home') }}">
                        <input class="ev-home-input" type="search" name="event_name" value="{{ $eventName }}" placeholder="Search by event name">
                        <select class="ev-home-input js-location-select" name="event_location" data-placeholder="Select location">
                            <option value="">All locations</option>
                            @foreach($locations as $location)
                                <option value="{{ $location }}" @selected($eventLocation === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                        <input class="ev-home-input js-event-date" type="text" name="event_date" value="{{ $eventDate }}" placeholder="Select date" autocomplete="off">
                        <button class="ev-home-search-btn" type="submit">Search</button>
                        <a class="ev-home-clear-btn" href="{{ route('front.home') }}">Clear</a>
                    </form>
                </div>
            </div>

            <!--Main Content Wrap Start-->
            <style>
            @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');
            @import url('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            @import url('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');

            .banner_slider .slide {
                position: relative;
                height: clamp(320px, 50vw, 560px);
                overflow: hidden;
            }

            .banner_slider .slide > img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
                display: block;
            }

            .banner_slider .banner-slide-link {
                display: block;
                width: 100%;
                height: 100%;
            }

            .banner_slider .slide::before,
            .banner_slider .slick-slide::before {
                content: none !important;
                display: none !important;
                background: transparent !important;
            }

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
            .ev-home-section.ev-home-section--upcoming {
                padding-top: 28px;
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


            .ev-home-search-wrap {
                background: var(--ev-bg);
                padding: 30px 0 2px;
                font-family: var(--ev-font-b);
            }

            .ev-home-search {
                margin: 0 0 20px;
                border: 1px solid var(--ev-border);
                border-radius: var(--ev-radius);
                background: var(--ev-surface);
                padding: 14px;
                display: grid;
                grid-template-columns: 1.2fr 1fr 0.8fr auto auto;
                gap: 10px;
                align-items: center;
            }
            .ev-home-input {
                width: 100%;
                background: var(--ev-surface2);
                border: 1px solid rgba(255,255,255,0.03);
                color: var(--ev-text);
                border-radius: 10px;
                padding: 11px 12px;
                font-size: 13px;
                box-shadow: none;
                appearance: none;
                -webkit-appearance: none;
                background-clip: padding-box;
            }
            .ev-home-input::placeholder {
                color: rgba(232,232,239,0.45);
            }
            .ev-home-input:focus {
                outline: none;
                border-color: rgba(245,184,0,0.45);
                box-shadow: 0 0 0 3px rgba(245,184,0,0.08);
            }
            .ev-home-search-btn,
            .ev-home-clear-btn {
                border-radius: 10px;
                border: 1px solid transparent;
                padding: 10px 14px;
                font-family: var(--ev-font-h);
                letter-spacing: 1px;
                text-transform: uppercase;
                font-size: 11px;
                font-weight: 800;
                text-decoration: none;
                white-space: nowrap;
                text-align: center;
            }
            .ev-home-search-btn {
                background: var(--ev-gold);
                color: #000;
            }
            .ev-home-clear-btn {
                background: transparent;
                color: var(--ev-muted);
                border-color: var(--ev-border);
            }
            .ev-home-search-btn:hover { background: #ffc820; }
            .ev-home-clear-btn:hover { color: var(--ev-gold); border-color: rgba(245,184,0,0.35); }
            @media (max-width: 992px) {
                .ev-home-search {
                    grid-template-columns: 1fr 1fr;
                }
            }
            @media (max-width: 680px) {
                .ev-home-search {
                    grid-template-columns: 1fr;
                    padding: 12px;
                }
                .ev-home-search-btn,
                .ev-home-clear-btn {
                    width: 100%;
                    min-height: 42px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }
            }


            .select2-container--default .select2-selection--single {
                height: 44px;
                background: var(--ev-surface2);
                border: 1px solid rgba(255,255,255,0.03);
                border-radius: 10px;
            }
            .select2-container--default.select2-container--focus .select2-selection--single,
            .select2-container--default.select2-container--open .select2-selection--single {
                border-color: rgba(245,184,0,0.45);
                box-shadow: 0 0 0 3px rgba(245,184,0,0.08);
            }
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: var(--ev-text);
                line-height: 42px;
                padding-left: 12px;
                font-size: 13px;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 42px;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow b {
                border-color: var(--ev-muted) transparent transparent transparent;
            }
            .select2-dropdown {
                background: var(--ev-surface2);
                border: 1px solid var(--ev-border);
            }
            .select2-search--dropdown .select2-search__field {
                background: #0f0f16;
                color: var(--ev-text);
                border: 1px solid var(--ev-border);
            }
            .select2-results__option { color: var(--ev-text); }
            .select2-container { width: 100% !important; }
            .select2-container--open { z-index: 9999; }
            .select2-container--default .select2-results__option[aria-selected=true] {
                background: transparent;
                color: #cfd0dc;
            }
            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background: rgba(245,184,0,0.2);
                color: #fff;
            }
            .flatpickr-calendar {
                background: #111118;
                border: 1px solid var(--ev-border);
                box-shadow: 0 12px 30px rgba(0,0,0,0.45);
            }
            .flatpickr-day,
            .flatpickr-current-month,
            .flatpickr-weekday { color: #e8e8ef; }
            .flatpickr-day.selected,
            .flatpickr-day.startRange,
            .flatpickr-day.endRange {
                background: var(--ev-gold);
                border-color: var(--ev-gold);
                color: #000;
            }
            .flatpickr-day.today {
                border-color: rgba(245,184,0,0.45);
                color: var(--ev-gold);
            }
            .flatpickr-day.today:not(.selected) {
                background: transparent;
            }
            .flatpickr-day:focus,
            .flatpickr-day:hover {
                background: rgba(245,184,0,0.16);
                border-color: rgba(245,184,0,0.28);
                color: #fff;
            }
            .flatpickr-day.selected:focus,
            .flatpickr-day.selected:hover {
                background: var(--ev-gold);
                border-color: var(--ev-gold);
                color: #000;
            }

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

                        <section class="ev-home-section ev-home-section--upcoming">
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
                            @if(! $hasHomeFilters)
                                <div class="ev-home-empty">
                                    <div class="ev-home-empty-icon">🎫</div>
                                    <h3>No upcoming events yet</h3>
                                    <p>Stay tuned — new dates coming soon.</p>
                                    <a class="ev-home-empty-btn" href="{{ route('front.events') }}">Explore Events →</a>
                                </div>
                            @else
                                <div class="ev-home-empty">
                                    <div class="ev-home-empty-icon">🔎</div>
                                    <h3>No upcoming results</h3>
                                    <p>Try another name, location, or date to find upcoming events.</p>
                                </div>
                            @endif
                        @endforelse
                    </div>

                </div>
            </section>

            @if(! $hasHomeFilters || $previousEvents->isNotEmpty())
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
                                            {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}
                                        </span>
                                        <span class="ev-home-pill">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            {{ $event->location }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                        @endforelse
                    </div>
                </div>
            </section>
            @endif
            <!--Main Content Wrap End-->



<script>
    (function () {
        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = reject;
                document.body.appendChild(script);
            });
        }

        function initLocationSelect() {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            var $select = window.jQuery('.js-location-select');
            if (! $select.length || $select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                width: '100%',
                placeholder: 'Select location',
                allowClear: true,
            });
        }

        function initDatePicker() {
            if (!window.flatpickr) {
                return;
            }

            var dateField = document.querySelector('.js-event-date');
            if (!dateField || dateField.dataset.flatpickrReady === '1') {
                return;
            }

            window.flatpickr(dateField, {
                dateFormat: 'Y-m-d',
                allowInput: true,
            });
            dateField.dataset.flatpickrReady = '1';
        }

        window.addEventListener('load', function () {
            var loadSelect2 = Promise.resolve();
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                loadSelect2 = loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
            }

            var loadFlatpickr = Promise.resolve();
            if (!window.flatpickr) {
                loadFlatpickr = loadScript('https://cdn.jsdelivr.net/npm/flatpickr');
            }

            loadSelect2.then(initLocationSelect).catch(function () {});
            loadFlatpickr.then(initDatePicker).catch(function () {});
        });
    })();
</script>

@endsection
