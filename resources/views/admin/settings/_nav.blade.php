@php
    $u = auth()->user();
    $tabs = [];
    foreach (\App\Settings\Registry::GROUPS as $key => $g) {
        if ($key === 'payments') { continue; } // shown as the "Payments" tab (methods, providers, webhooks, routing)
        if ($u->can($g['permission'])) {
            $tabs[] = [$g['label'], $key === 'general' ? route('admin.settings.edit') : route('admin.settings.section', $key), request()->is('dashboard/settings/'.$key) || ($key === 'general' && request()->routeIs('admin.settings.edit'))];
        }
    }
    if ($u->canAny(['payment-methods.view', 'payments.accounts.view', 'settings.integrations.view', 'settings.payment-accounts.view', 'payments.webhooks.view'])) {
        $first = $u->can('payment-methods.view') ? route('admin.payment-methods.index') : route('admin.payments.providers');
        $tabs[] = ['Payments', $first, request()->routeIs('admin.payment-methods.*', 'admin.payments.providers', 'admin.payments.webhooks*') || request()->is('dashboard/settings/payments')];
    }
    if ($u->can('settings.integrations.view')) { $tabs[] = ['Integrations', route('admin.integrations.index'), request()->routeIs('admin.integrations.*')]; }
@endphp
<nav class="a-tabs" aria-label="Settings sections">
    @foreach($tabs as [$label, $url, $active])
        <a href="{{ $url }}" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
