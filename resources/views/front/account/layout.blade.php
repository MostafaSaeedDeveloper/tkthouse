@extends('front.layout.app')

@push('styles')
    <link href="{{ asset('css/tkt-portal.css') }}?v={{ filemtime(public_path('css/tkt-portal.css')) }}" rel="stylesheet">
@endpush

@php
    $portalUser = auth()->user();
    $initial = mb_strtoupper(mb_substr(trim((string) ($portalUser->name ?: $portalUser->email)), 0, 1));
    $tabs = [
        ['route' => 'front.account.orders', 'match' => 'front.account.orders*', 'label' => 'My orders'],
        ['route' => 'front.account.tickets', 'match' => 'front.account.tickets', 'label' => 'My tickets'],
        ['route' => 'front.account.profile', 'match' => 'front.account.profile', 'label' => 'My account'],
    ];
@endphp

@section('content')
<div class="tkt-container tkt-portal">
    <header class="tkt-portal__head">
        <div class="tkt-portal__who">
            <span class="tkt-portal__avatar" aria-hidden="true">{{ $initial }}</span>
            <div class="tkt-portal__who-text">
                <h1 class="tkt-portal__name">@yield('heading', 'My account')</h1>
                <p class="tkt-portal__email">{{ $portalUser->name ?: 'Welcome' }} · {{ $portalUser->email }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('front.customer.logout') }}" class="tkt-portal__logout">
            @csrf
            <button type="submit" class="tkt-btn tkt-btn--outline tkt-btn--sm">Log out</button>
        </form>
    </header>

    <nav class="tkt-portal__tabs" aria-label="Account">
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="{{ request()->routeIs($tab['match']) ? 'is-active' : '' }}" @if(request()->routeIs($tab['match'])) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>

    @if(session('success'))
        <div class="tkt-alert tkt-alert--success" role="status" style="margin-bottom:16px">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="tkt-alert tkt-alert--danger" role="alert" style="margin-bottom:16px">{{ session('error') }}</div>
    @endif

    @yield('portal')
</div>
@endsection
