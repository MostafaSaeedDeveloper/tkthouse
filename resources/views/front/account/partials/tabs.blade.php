@php
    $tabs = [
        'front.account.dashboard' => 'Overview',
        'front.account.profile' => 'Profile',
        'front.account.orders' => 'Orders',
        'front.account.tickets' => 'Tickets',
    ];
@endphp

<ul class="nav nav-tabs mb-4 border-secondary">
    @foreach($tabs as $route => $label)
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'active bg-warning text-dark border-warning' : 'text-white border-secondary' }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
</ul>
