@php
    $items = [
        ['route' => 'front.account.profile', 'label' => 'Profile', 'icon' => 'si si-user'],
        ['route' => 'front.account.orders', 'label' => 'Orders', 'icon' => 'si si-basket-loaded'],
        ['route' => 'front.account.tickets', 'label' => 'Tickets', 'icon' => 'si si-ticket'],
    ];
@endphp

<div class="card bg-dark border-secondary mb-4">
    <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="d-flex flex-wrap gap-2">
            @foreach($items as $item)
                @php($isActive = request()->routeIs($item['route']))
                <a href="{{ route($item['route']) }}" class="btn {{ $isActive ? 'btn-warning text-dark' : 'btn-outline-light' }}">
                    <i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('front.customer.logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="si si-logout me-1"></i>Logout
            </button>
        </form>
    </div>
</div>
