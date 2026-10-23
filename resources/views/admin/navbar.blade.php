{{-- Admin V2 sidebar. Items follow the real permissions; nothing is hidden by CSS only (the routes enforce them on the server too). --}}
@php
    $u = auth()->user();
    $can = fn (string $p) => (bool) $u?->can($p);
    $on = fn (...$patterns) => request()->routeIs(...$patterns);

    $main = array_values(array_filter([
        $can('dashboard.view') ? ['Dashboard', 'admin.dashboard', 'fa-gauge-high', ['admin.dashboard']] : null,
        $can('events.view') ? ['Events', 'admin.events.index', 'fa-calendar-days', ['admin.events.*']] : null,
        $can('orders.view') ? ['Orders', 'admin.orders.index', 'fa-receipt', ['admin.orders.index', 'admin.orders.show', 'admin.orders.edit', 'admin.orders.deleted']] : null,
        $can('tickets.view') ? ['Tickets', 'admin.tickets.index', 'fa-ticket', ['admin.tickets.index', 'admin.tickets.show', 'admin.tickets.edit', 'admin.tickets.create']] : null,
        $can('attendees.view') ? ['Customers', 'admin.customers.index', 'fa-users', ['admin.customers.*']] : null,
        $can('promo-codes.view') ? ['Promo Codes', 'admin.promo-codes.index', 'fa-tags', ['admin.promo-codes.*']] : null,
        ($can('orders.view') || $can('payments.transactions.view')) ? ['Payments', 'admin.payments.index', 'fa-credit-card', ['admin.payments.index', 'admin.payments.transactions*', 'admin.payments.refunds*']] : null,
        ($can('reports.view') && ! \App\Orders\OrderAccess::scoped($u)) ? ['Reports', 'admin.reports.index', 'fa-chart-column', ['admin.reports.index']] : null,
        (\App\Orders\OrderAccess::enabled() && \App\Orders\OrderAccess::level($u) !== \App\Orders\OrderAccess::NONE && $can('orders.view')) ? [\App\Orders\OrderAccess::scoped($u) ? 'Agent Reports' : 'Agent report', \App\Orders\OrderAccess::scoped($u) ? 'admin.reports.mine' : 'admin.reports.agents', 'fa-chart-line', ['admin.reports.agents', 'admin.reports.mine']] : null,
    ]));

    $ops = array_values(array_filter([
        $can('guest-list.view') ? ['Guest list', 'admin.guest-list.index', 'fa-clipboard-list', ['admin.guest-list.*']] : null,
        $can('scanner.access') ? ['Ticket scanner', 'admin.tickets.scanner', 'fa-qrcode', ['admin.tickets.scanner*']] : null,
        $can('scanners.view') ? ['Scanner staff', 'admin.scanners.index', 'fa-id-badge', ['admin.scanners.*']] : null,
        $can('attendees.view') ? ['Affiliates', 'admin.affiliates.index', 'fa-link', ['admin.affiliates.*']] : null,
    ]));

    $team = array_values(array_filter([
        $can('users.view') ? ['Users', 'admin.users.index', 'fa-user-shield', ['admin.users.*']] : null,
        $can('roles.view') ? ['Roles', 'admin.roles.index', 'fa-key', ['admin.roles.*']] : null,
        $can('permissions.view') ? ['Permissions', 'admin.permissions.index', 'fa-lock', ['admin.permissions.*']] : null,
        $can('activity-logs.view') ? ['Activity log', 'admin.activity-logs.index', 'fa-clock-rotate-left', ['admin.activity-logs.*']] : null,
    ]));

    $settings = array_values(array_filter([
        $can('settings.view') ? ['Settings Center', 'admin.settings.edit', ['admin.settings.*']] : null,
        $can('settings.integrations.view') ? ['Integrations', 'admin.integrations.index', ['admin.integrations.*']] : null,
        ($can('payment-methods.view') || $can('payments.accounts.view')) ? ['Payments settings', $can('payment-methods.view') ? 'admin.payment-methods.index' : 'admin.payments.providers', ['admin.payment-methods.*', 'admin.payments.providers', 'admin.payments.webhooks*']] : null,
    ]));
    $settingsOpen = collect($settings)->contains(fn ($s) => $on(...$s[2]));
    $teamOpen = collect($team)->contains(fn ($s) => $on(...$s[3]));

    $link = function ($item, $sub = false) use ($on) {
        [$label, $route, $icon, $patterns] = array_pad($item, 4, null);
        return compact('label', 'route', 'icon', 'patterns');
    };
@endphp

<ul class="a-nav">
    @foreach($main as [$label, $route, $icon, $patterns])
        <li class="a-nav__item">
            <a class="a-nav__link {{ $on(...$patterns) ? 'is-active' : '' }}" href="{{ route($route) }}" @if($on(...$patterns)) aria-current="page" @endif>
                <i class="fa {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
            </a>
        </li>
    @endforeach

    @if($ops)
        <li class="a-nav__heading" aria-hidden="true">Operations</li>
        @foreach($ops as [$label, $route, $icon, $patterns])
            <li class="a-nav__item">
                <a class="a-nav__link {{ $on(...$patterns) ? 'is-active' : '' }}" href="{{ route($route) }}" @if($on(...$patterns)) aria-current="page" @endif>
                    <i class="fa {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                </a>
            </li>
        @endforeach
    @endif

    @if($team || $settings)
        <li class="a-nav__heading" aria-hidden="true">System</li>
    @endif

    @if($team)
        <li class="a-nav__item {{ $teamOpen ? 'is-open' : '' }}">
            <button type="button" class="a-nav__link" data-a-submenu aria-expanded="{{ $teamOpen ? 'true' : 'false' }}">
                <i class="fa fa-user-gear" aria-hidden="true"></i><span>Team &amp; access</span><i class="fa fa-chevron-down a-nav__caret" aria-hidden="true"></i>
            </button>
            <ul class="a-nav__sub">
                @foreach($team as [$label, $route, $icon, $patterns])
                    <li><a class="a-nav__link {{ $on(...$patterns) ? 'is-active' : '' }}" href="{{ route($route) }}" @if($on(...$patterns)) aria-current="page" @endif><span>{{ $label }}</span></a></li>
                @endforeach
            </ul>
        </li>
    @endif

    @if($settings)
        <li class="a-nav__item {{ $settingsOpen ? 'is-open' : '' }}">
            <button type="button" class="a-nav__link" data-a-submenu aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}">
                <i class="fa fa-gear" aria-hidden="true"></i><span>Settings</span><i class="fa fa-chevron-down a-nav__caret" aria-hidden="true"></i>
            </button>
            <ul class="a-nav__sub">
                @foreach($settings as [$label, $route, $patterns])
                    <li><a class="a-nav__link {{ $on(...$patterns) ? 'is-active' : '' }}" href="{{ route($route) }}" @if($on(...$patterns)) aria-current="page" @endif><span>{{ $label }}</span></a></li>
                @endforeach
            </ul>
        </li>
    @endif
</ul>
