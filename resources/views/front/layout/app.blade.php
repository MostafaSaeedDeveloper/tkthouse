@php
    $siteName = \App\Support\SystemSettings::get('site_name', 'TKT House');
    $logoLight = \App\Support\SystemSettings::get('site_logo_light') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_light')) : asset('images/logo-light.png');
    $logoFooter = \App\Support\SystemSettings::get('site_logo_footer') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_footer')) : asset('images/footer-logo.png');
    // Brand colour can still be overridden from the admin settings; default is the V2 brand yellow.
    $brandColor = \App\Support\SystemSettings::get('primary_color');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' | '.$siteName : $siteName.' | Events & Ticket Booking';
    $metaDescription = trim($__env->yieldContent('description')) ?: 'TKT House curates premium techno events and makes booking your tickets simple and secure.';
    $ogImage = trim($__env->yieldContent('og_image')) ?: $logoLight;
    // Canonical: the page itself, keeping only the pagination parameters (search/filter URLs point to the base listing).
    $canonicalQuery = collect(['page', 'previous_page'])->mapWithKeys(fn ($k) => [$k => request()->query($k)])->filter(fn ($v) => (int) $v > 1)->all();
    $canonicalUrl = url()->current().($canonicalQuery ? '?'.http_build_query($canonicalQuery) : '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    @hasSection('meta')
        @yield('meta')
    @endif

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

    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Bootstrap 3 is kept for the grid helpers, modal & JS plugins already used by the auth modal. --}}
    <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('css/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('css/tkt-v2.css') }}?v={{ filemtime(public_path('css/tkt-v2.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/tkt-v2-pages.css') }}?v={{ filemtime(public_path('css/tkt-v2-pages.css')) }}" rel="stylesheet">
    @if($brandColor)
        <style>:root{--tkt-primary: {{ $brandColor }};}</style>
    @endif
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/css/intlTelInput.css">
    @stack('styles')
    {!! \App\Support\SystemSettings::get('custom_code_head') !!}
</head>
<body class="tkt-v2" data-layout="v2" data-authenticated="{{ auth()->check() ? '1' : '0' }}">
    {!! \App\Support\SystemSettings::get('custom_code_body') !!}
    <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=1320872626597581&ev=PageView&noscript=1" alt="">
    </noscript>

    <a class="tkt-sr-only" href="#tkt-main">Skip to content</a>

    @include('front.layout.partials.v2-header', ['siteName' => $siteName, 'logoLight' => $logoLight])

    <main id="tkt-main" class="tkt-page">
        @if(session('success') || session('error') || session('status'))
            <div class="tkt-container tkt-flash">
                @if(session('success'))<div class="tkt-alert tkt-alert--success" role="status">{{ session('success') }}</div>@endif
                @if(session('status'))<div class="tkt-alert tkt-alert--success" role="status">{{ session('status') }}</div>@endif
                @if(session('error'))<div class="tkt-alert tkt-alert--danger" role="alert">{{ session('error') }}</div>@endif
            </div>
        @endif
        @yield('content')
    </main>

    @include('front.layout.partials.v2-footer', ['siteName' => $siteName, 'logoFooter' => $logoFooter])

    <div class="tkt-toast" id="tktToast" role="status" aria-live="polite"></div>

    @include('front.partials.auth-modal-v2')

    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/js/intlTelInput.min.js"></script>
    <script src="{{ asset('js/bootstrap.js') }}"></script>
    <script src="{{ asset('js/tkt-page.js') }}?v={{ filemtime(public_path('js/tkt-page.js')) }}"></script>
    <script src="{{ asset('js/tkt-v2.js') }}?v={{ filemtime(public_path('js/tkt-v2.js')) }}"></script>
    <script src="{{ asset('js/tkt-booking.js') }}?v={{ filemtime(public_path('js/tkt-booking.js')) }}"></script>
    {{-- Soft navigation between the public pages (see docs/spa-navigation-audit.md). Vendored, version-pinned. --}}
    <script src="{{ asset('js/vendor/swup-4.8.2.umd.js') }}"></script>
    <script src="{{ asset('js/vendor/swup-head-plugin-2.3.1.umd.js') }}"></script>
    <script src="{{ asset('js/vendor/swup-a11y-plugin-5.2.1.umd.js') }}"></script>
    <script src="{{ asset('js/vendor/swup-scroll-plugin-4.0.0.umd.js') }}"></script>
    <script src="{{ asset('js/vendor/swup-progress-plugin-3.2.0.umd.js') }}"></script>
    <script src="{{ asset('js/tkt-spa.js') }}?v={{ filemtime(public_path('js/tkt-spa.js')) }}"></script>
    @include('front.partials.auth-scripts')
    @stack('scripts')
    {!! \App\Support\SystemSettings::get('custom_code_footer') !!}
</body>
</html>
