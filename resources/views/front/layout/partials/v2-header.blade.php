@php
    $navItems = [
        ['label' => 'Home', 'route' => 'front.home', 'active' => request()->routeIs('front.home')],
        ['label' => 'About', 'route' => 'front.about', 'active' => request()->routeIs('front.about')],
        ['label' => 'Events', 'route' => 'front.events', 'active' => request()->routeIs('front.events', 'front.events.show')],
        ['label' => 'Contact', 'route' => 'front.contact', 'active' => request()->routeIs('front.contact')],
    ];
    $headerUser = auth()->user();
    $initial = $headerUser ? mb_strtoupper(mb_substr(trim((string) ($headerUser->name ?: $headerUser->email)), 0, 1)) : '';
@endphp
<header class="tkt-header" id="tktHeader">
    <div class="tkt-container">
        <div class="tkt-header__bar">
            <a class="tkt-logo" href="{{ route('front.home') }}" aria-label="{{ $siteName }} home">
                <img src="{{ $logoLight }}" alt="{{ $siteName }}">
            </a>

            <nav class="tkt-nav" aria-label="Main">
                @foreach($navItems as $item)
                    <a href="{{ route($item['route']) }}" class="{{ $item['active'] ? 'is-active' : '' }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <form class="tkt-header__search" method="GET" action="{{ route('front.events') }}" role="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="q" value="{{ request()->routeIs('front.events') ? request('q') : '' }}" placeholder="Search events, artists or venues…" aria-label="Search events" maxlength="120">
            </form>

            <div class="tkt-header__actions">
                @auth
                    <div class="tkt-account" id="tktAccount">
                        <button type="button" class="tkt-account__toggle" aria-haspopup="true" aria-expanded="false">
                            <span class="tkt-account__avatar">{{ $initial }}</span>
                            <span class="tkt-account__name">{{ $headerUser->name ?: 'My account' }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="tkt-account__menu" role="menu">
                            <a href="{{ route('front.account.profile') }}" role="menuitem">My account</a>
                            <a href="{{ route('front.account.orders') }}" role="menuitem">My orders</a>
                            <a href="{{ route('front.account.tickets') }}" role="menuitem">My tickets</a>
                            <hr>
                            <form method="POST" action="{{ route('front.customer.logout') }}">
                                @csrf
                                <button type="submit" role="menuitem">Log out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="tkt-header__guest">
                        <button type="button" class="tkt-btn tkt-btn--outline-light tkt-btn--sm" data-toggle="modal" data-target="#login-register1" data-auth-open="login">Log in</button>
                        <button type="button" class="tkt-btn tkt-btn--primary tkt-btn--sm" data-toggle="modal" data-target="#login-register1" data-auth-open="register">Sign up</button>
                    </div>
                @endauth

                <button type="button" class="tkt-burger" id="tktBurger" aria-label="Toggle menu" aria-expanded="false" aria-controls="tktMobilePanel">
                    <svg class="tkt-ico-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg class="tkt-ico-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="tkt-mobile-panel" id="tktMobilePanel">
        <form class="tkt-header__search" method="GET" action="{{ route('front.events') }}" role="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="q" placeholder="Search events…" aria-label="Search events" maxlength="120">
        </form>
        <nav aria-label="Mobile">
            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}" class="{{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
        @guest
            <div class="tkt-mobile-panel__guest">
                <button type="button" class="tkt-btn tkt-btn--outline-light" data-toggle="modal" data-target="#login-register1" data-auth-open="login">Log in</button>
                <button type="button" class="tkt-btn tkt-btn--primary" data-toggle="modal" data-target="#login-register1" data-auth-open="register">Sign up</button>
            </div>
        @endguest
    </div>
</header>
