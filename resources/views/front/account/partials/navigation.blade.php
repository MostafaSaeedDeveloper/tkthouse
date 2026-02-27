@php
    $items = [
        ['route' => 'front.account.profile', 'label' => 'Profile',  'icon' => 'fa fa-user'],
        ['route' => 'front.account.orders',  'label' => 'Orders',   'icon' => 'fa fa-bag-shopping'],
        ['route' => 'front.account.tickets', 'label' => 'Tickets',  'icon' => 'fa fa-ticket'],
    ];
@endphp

<nav class="acc-nav">
    <div class="acc-nav-links">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="acc-nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="{{ $item['icon'] }}"></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('front.customer.logout') }}" style="margin:0;">
        @csrf
        <button type="submit" class="acc-nav-logout">
            <i class="fa fa-right-from-bracket"></i>
            Logout
        </button>
    </form>
</nav>
