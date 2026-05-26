<!DOCTYPE html>
@php
    $siteName = \App\Support\SystemSettings::get('site_name', 'TKT House');
    $primaryColor = \App\Support\SystemSettings::get('primary_color', '#f5b800');
    $secondaryColor = \App\Support\SystemSettings::get('secondary_color', '#111111');
    $logoLight = \App\Support\SystemSettings::get('site_logo_light') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_light')) : asset('images/logo-light.png');
    $logoDark = \App\Support\SystemSettings::get('site_logo_dark') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_dark')) : asset('images/logo-dark.png');
@endphp

<html lang="en">
<head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-2321WHVMSX"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-2321WHVMSX');
        </script>
        <!-- Meta Pixel Code -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '1320872626597581');
            fbq('track', 'PageView');
        </script>
        <!-- End Meta Pixel Code -->

        <title>{{ $siteName }} | Techno Events & Ticket Booking</title>
        <!-- Bootstrap core CSS -->
        <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet">
        <!-- Preloader CSS -->
        <link rel="stylesheet" href="{{ asset('css/preloader.css') }}">
        <!-- DL Menu CSS -->
        <link href="{{ asset('js/dl-menu/component.css') }}" rel="stylesheet">
        <!-- Slick Slider CSS -->
        <link href="{{ asset('css/slick.css') }}" rel="stylesheet"/>
        <link href="{{ asset('css/slick-theme.css') }}" rel="stylesheet"/>
        <!-- jquery.bxslider CSS -->
        <link href="{{ asset('css/jquery.bxslider.css') }}" rel="stylesheet">
        <!--Player Css-->
        <link href="{{ asset('js/jplayer/jplayer.uno.css') }}" rel="stylesheet" />
        <!--black-style Css-->
        <link href="{{ asset('css/black-style.css') }}" rel="stylesheet" />
        <!-- Fav icon -->
        <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon.png') }}">
        <link href="{{ asset('css/font-awesome.css') }}" rel="stylesheet">
        <link href="{{ asset('css/svg-icons.css') }}" rel="stylesheet">
        <!-- Pretty Photo CSS -->
        <link href="{{ asset('css/prettyPhoto.css') }}" rel="stylesheet">
        <!-- animation CSS -->
        <link href="{{ asset('css/animation.css') }}" rel="stylesheet">
        <!-- Range slider CSS -->
        <link href="{{ asset('css/range-slider.css') }}" rel="stylesheet">
        <!-- Typography CSS -->
        <link href="{{ asset('css/typography.css') }}" rel="stylesheet">
        <!-- Widget CSS -->
        <link href="{{ asset('css/widget.css') }}" rel="stylesheet">
        <!-- Shortcodes CSS -->
        <link href="{{ asset('css/shortcodes.css') }}" rel="stylesheet">
        <!-- Custom Main StyleSheet CSS -->
        <link href="{{ asset('style.css') }}" rel="stylesheet">
        <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
        <style>:root{--gold: {{ $primaryColor }};--theme-dark: {{ $secondaryColor }};}</style>
        <!-- Color CSS -->
        <link href="{{ asset('css/color.css') }}" rel="stylesheet">

        <!-- Responsive CSS -->
        <link href="{{ asset('css/responsive.css') }}" rel="stylesheet">

        <!-- intl-tel-input -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/css/intlTelInput.css">
        <style>
        /* ── intl-tel-input dark theme ── */
        .iti { display: block !important; width: 100%; }

        /* Flag trigger button */
        .iti__selected-country {
            background: transparent !important;
            border-right: 1px solid rgba(255,255,255,0.09) !important;
            padding: 0 8px 0 8px !important;
            gap: 3px !important;
            display: flex !important;
            align-items: center !important;
        }
        /* reorder: flag → dial code → arrow */
        .iti__selected-country .iti__flag        { order: 1 !important; }
        .iti__selected-country .iti__selected-dial-code { order: 2 !important; }
        .iti__selected-country .iti__arrow       { order: 3 !important; margin-left: 3px !important; }

        .iti__selected-country:hover,
        .iti__selected-country[aria-expanded="true"] { background: rgba(215,166,0,0.08) !important; }
        .iti__selected-dial-code { color: #dbe4ff !important; font-size: 13px !important; font-weight: 500 !important; }
        .iti__arrow { border-top-color: #7e849b !important; }
        .iti--open .iti__arrow { border-bottom-color: #7e849b !important; }

        /* ── Nuke every possible white wrapper the library injects ── */
        .iti__flag-container   { background: transparent !important; }

        /* v22 wraps search+list in iti__dropdown-content */
        .iti__dropdown-content {
            background: #181821 !important;
            border: 1px solid rgba(255,255,255,0.35) !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            box-shadow: 0 16px 48px rgba(0,0,0,0.75) !important;
            z-index: 999999 !important;
            padding: 0 !important;
        }

        /* The <ul> itself — no extra border, parent handles it */
        .iti__country-list {
            background: #181821 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            z-index: 999999 !important;
            max-height: 280px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding: 4px 0 !important;
            margin: 0 !important;
            /* fallback border when iti__dropdown-content absent */
            outline: 1px solid rgba(255,255,255,0.35) !important;
            outline-offset: -1px !important;
        }
        .iti__country-list::-webkit-scrollbar { width: 4px; }
        .iti__country-list::-webkit-scrollbar-track { background: transparent; }
        .iti__country-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.14); border-radius: 99px; }

        /* Search input */
        .iti__search-input-wrapper { background: #181821 !important; padding: 8px 10px 6px !important; }
        .iti__search-input {
            display: block !important;
            width: 100% !important;
            height: 44px !important;
            background: #0f0f18 !important;
            border: 1px solid rgba(255,255,255,0.13) !important;
            border-radius: 10px !important;
            color: #dbe4ff !important;
            font-size: 13px !important;
            padding: 0 14px !important;
            outline: none !important;
            box-sizing: border-box !important;
            transition: border-color .18s !important;
            margin: 0 !important;
        }
        .iti__search-input:focus { border-color: rgba(215,166,0,0.5) !important; }
        .iti__search-input::placeholder { color: #7e849b !important; }

        /* Divider */
        .iti__divider { border-color: rgba(255,255,255,0.08) !important; margin: 2px 0 !important; }

        /* Country rows */
        .iti__country {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 0 14px !important;
            height: 46px !important;
            color: #dbe4ff !important;
            font-size: 13px !important;
            background: transparent !important;
            transition: background .1s !important;
        }
        .iti__country:hover    { background: rgba(214,166,0,0.18) !important; }
        .iti__country.iti__highlight { background: rgba(214,166,0,0.18) !important; }
        .iti__country.iti__active    { background: rgba(214,166,0,0.10) !important; }

        .iti__flag-box   { flex-shrink: 0 !important; }
        .iti__country-name {
            color: #dbe4ff !important;
            flex: 1 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .iti__dial-code { color: #7e849b !important; font-size: 12px !important; flex-shrink: 0 !important; }
        </style>
    </head>

    <body class="msl-black" data-authenticated="{{ auth()->check() ? "1" : "0" }}">
        <noscript>
            <img height="1" width="1" style="display:none"
                 src="https://www.facebook.com/tr?id=1320872626597581&ev=PageView&noscript=1"
                 alt="" />
        </noscript>
        <div id="loader-wrapper">
            <div id="loader"></div>
            <div class="loader-section section-left"></div>
            <div class="loader-section section-right"></div>
        </div>

        <div class="kode_wrapper">
            <header class="header-style-3">
                <div class="header-2st-row ">
                    <div class="container">
                        <div class="pull-left">
                            <div class="social-icons">
                                <strong>FOLLOW US:</strong>
                                <ul>
                                    <li><a href="https://www.instagram.com/tkthouse.eg?igsh=MTd0MnJjcWJ4bG9yZA==" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa fa-instagram" aria-hidden="true"></i></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="center-logo">
                            <div class="logo">
                                <h1><a href="{{ route('front.home') }}"><img class="logo-light" src="{{ $logoLight }}" alt="{{ $siteName }}"><img class="logo-drak" src="{{ $logoDark }}" alt="{{ $siteName }}"></a></h1>
                            </div>
                        </div>
                        <div class="pull-right">
                            <ul class="playlist_menu_bar">
                                @auth
                                    <li><a href="{{ route('front.account.profile') }}" title="My Dashboard"><i class="fa fa-user-circle"></i></a></li>
                                @else
                                    <li><a href="#" data-toggle="modal" data-target="#login-register1" title="Customer Login"><i class="fa fa-user-circle"></i></a></li>
                                @endauth
                            </ul>
                            <div id="kode-responsive-navigation" class="dl-menuwrapper">
                                <button class="dl-trigger"></button>
                                <ul class="dl-menu">
                                    <li><a href="{{ route('front.home') }}">Home</a></li>
                                    <li><a href="{{ route('front.about') }}">About</a></li>
                                    <li><a href="{{ route('front.events') }}">Events</a></li>
                                    <li><a href="{{ route('front.contact') }}">Contact</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-2st-row align-center-nav">
                    <div class="container">
                        <div class="fst-navigation">
                            <nav class="navigation-1">
                                <ul>
                                    <li><a href="{{ route('front.home') }}">Home</a></li>
                                    <li><a href="{{ route('front.about') }}">About</a></li>
                                    <li><a href="{{ route('front.events') }}">Events</a></li>
                                    <li><a href="{{ route('front.contact') }}">Contact</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </header>
