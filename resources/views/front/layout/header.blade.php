<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>TKTHouse | Techno Events & Ticket Booking</title>
        <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/preloader.css') }}">
        <link href="{{ asset('js/dl-menu/component.css') }}" rel="stylesheet">
        <link href="{{ asset('css/slick.css') }}" rel="stylesheet"/>
        <link href="{{ asset('css/slick-theme.css') }}" rel="stylesheet"/>
        <link href="{{ asset('css/jquery.bxslider.css') }}" rel="stylesheet">
        <link href="{{ asset('js/jplayer/jplayer.uno.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/black-style.css') }}" rel="stylesheet" />
        <link rel="icon" type="icon" sizes="96x96" href="{{ asset('fonts/fav.png') }}">
        <link href="{{ asset('css/font-awesome.css') }}" rel="stylesheet">
        <link href="{{ asset('css/svg-icons.css') }}" rel="stylesheet">
        <link href="{{ asset('css/prettyPhoto.css') }}" rel="stylesheet">
        <link href="{{ asset('css/animation.css') }}" rel="stylesheet">
        <link href="{{ asset('css/range-slider.css') }}" rel="stylesheet">
        <link href="{{ asset('css/typography.css') }}" rel="stylesheet">
        <link href="{{ asset('css/widget.css') }}" rel="stylesheet">
        <link href="{{ asset('css/shortcodes.css') }}" rel="stylesheet">
        <link href="{{ asset('style.css') }}" rel="stylesheet">
        <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
        <link href="{{ asset('css/color.css') }}" rel="stylesheet">
        <link href="{{ asset('css/responsive.css') }}" rel="stylesheet">
        <style>
            .sub-banner h6,
            .sub-banner p {
                color: #000 !important;
            }
        </style>
    </head>

    <body class="msl-black">
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
                                <strong>Follow Us:</strong>
                                <ul>
                                    <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                                    <li><a href="#"><i class="fa fa-instagram" aria-hidden="true"></i></a></li>
                                    <li><a href="#"><i class="fa fa-youtube" aria-hidden="true"></i></a></li>
                                    <li><a href="#"><i class="fa fa-soundcloud" aria-hidden="true"></i></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="center-logo">
                            <div class="logo">
                                <h1><a href="{{ route('home.front') }}"><img class="logo-light" src="{{ asset('images/logo-light.png') }}" alt="TKTHouse"></a></h1>
                            </div>
                        </div>
                        <div class="pull-right">
                            <ul class="playlist_menu_bar">
                                <li><a href="#" aria-label="Account"><i class="fa fa-user-circle"></i></a></li>
                            </ul>
                            <div id="kode-responsive-navigation" class="dl-menuwrapper">
                                <button class="dl-trigger"></button>
                                <ul class="dl-menu">
                                    <li><a href="{{ route('home.front') }}">Home</a></li>
                                    <li><a href="{{ route('about') }}">About</a></li>
                                    <li><a href="{{ route('events.index') }}">Events</a></li>
                                    <li><a href="{{ route('contact') }}">Contact</a></li>
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
                                    <li><a href="{{ route('home.front') }}">Home</a></li>
                                    <li><a href="{{ route('about') }}">About</a></li>
                                    <li><a href="{{ route('events.index') }}">Events</a></li>
                                    <li><a href="{{ route('contact') }}">Contact</a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </header>
