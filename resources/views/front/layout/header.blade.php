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
        /* intl-tel-input dark theme */
        .iti { display: block !important; width: 100%; }
        .iti__selected-country { background: transparent !important; border-right: 1px solid rgba(255,255,255,0.1) !important; }
        .iti__selected-country:hover { background: rgba(255,255,255,0.04) !important; }
        .iti__selected-dial-code { color: #c8c8d8 !important; font-size: 13px !important; }
        .iti__arrow { border-top-color: #6b6b7e !important; }
        .iti--open .iti__arrow { border-bottom-color: #6b6b7e !important; }
        .iti__country-list {
            background: #1a1a24 !important;
            border: 1px solid rgba(255,255,255,0.12) !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6) !important;
            border-radius: 8px !important;
            z-index: 99999 !important;
        }
        .iti__search-input {
            background: #111118 !important;
            border: none !important;
            border-bottom: 1px solid rgba(255,255,255,0.08) !important;
            color: #e8e8ef !important;
            padding: 9px 12px !important;
        }
        .iti__search-input::placeholder { color: #4a4a5e !important; }
        .iti__country { color: #c8c8d8 !important; }
        .iti__country:hover, .iti__country.iti__highlight { background: rgba(245,184,0,0.1) !important; }
        .iti__country-name { color: #c8c8d8 !important; }
        .iti__dial-code { color: #6b6b7e !important; }
        .iti__divider { border-color: rgba(255,255,255,0.07) !important; }
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
