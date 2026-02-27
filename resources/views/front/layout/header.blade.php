<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>TKT House | Techno Events & Ticket Booking</title>
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
        <link rel="icon" type="icon" sizes="96x96" href="{{ asset('fonts/fav.png') }}">
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
        <!-- Color CSS -->
        <link href="{{ asset('css/color.css') }}" rel="stylesheet">

        <!-- Responsive CSS -->
        <link href="{{ asset('css/responsive.css') }}" rel="stylesheet">

        <style>
            .mobile-account-icon { display: none; }

            @media (max-width: 767px) {
                .header-style-3 .header-2st-row > .container {
                    display: grid;
                    grid-template-columns: 44px 1fr 44px;
                    align-items: center;
                    column-gap: 12px;
                }
                .header-style-3 .center-logo {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    float: none;
                    width: 100%;
                    text-align: center;
                }
                .header-style-3 .header-2st-row .logo {
                    float: none;
                    margin: 0;
                    display: inline-block;
                }
                .header-style-3 .header-2st-row .logo img {
                    max-height: 54px;
                    width: auto;
                }
                .header-style-3 .header-2st-row .logo .logo-light { display: block; }
                .header-style-3 .header-2st-row .logo .logo-drak { display: none; }

                .mobile-account-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .mobile-account-icon a {
                    font-size: 38px;
                    line-height: 1;
                }

                .header-style-3 .playlist_menu_bar {
                    display: none !important;
                }
                .header-style-3 .header-2st-row .pull-right {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    float: none;
                    width: auto;
                }
                .header-style-3 .header-2st-row .pull-right .dl-menuwrapper {
                    float: none;
                    margin: 0;
                }
            }
        </style>
    </head>

    <body class="msl-black" data-authenticated="{{ auth()->check() ? "1" : "0" }}">
        <div id="loader-wrapper">
            <div id="loader"></div>
            <div class="loader-section section-left"></div>
            <div class="loader-section section-right"></div>
        </div>

        <div class="kode_wrapper">
            <header class="header-style-3">
                <div class="header-2st-row ">
                    <div class="container">
                        <div class="mobile-account-icon">
                            @auth
                                <a href="{{ route('front.account.profile') }}" title="My Dashboard"><i class="fa fa-user-circle"></i></a>
                            @else
                                <a href="#" data-toggle="modal" data-target="#login-register1" title="Customer Login"><i class="fa fa-user-circle"></i></a>
                            @endauth
                        </div>
                        <div class="center-logo">
                            <div class="logo">
                                <h1><a href="{{ route('front.home') }}"><img class="logo-light" src="{{ asset('images/logo-light.png') }}" alt="TKT House"><img class="logo-drak" src="{{ asset('images/logo-dark.png') }}" alt="TKT House"></a></h1>
                            </div>
                        </div>
                        <div class="pull-right">
                            <ul class="playlist_menu_bar">
                                @auth
                                    <li class="desktop-account"><a href="{{ route('front.account.profile') }}" title="My Dashboard"><i class="fa fa-user-circle"></i></a></li>
                                @else
                                    <li class="desktop-account"><a href="#" data-toggle="modal" data-target="#login-register1" title="Customer Login"><i class="fa fa-user-circle"></i></a></li>
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
