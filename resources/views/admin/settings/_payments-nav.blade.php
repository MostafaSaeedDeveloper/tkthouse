@php
    $u = auth()->user();
    $pt = [];
    if ($u->can('payment-methods.view')) { $pt[] = ['Payment Methods', route('admin.payment-methods.index'), request()->routeIs('admin.payment-methods.*')]; }
    if ($u->can('payments.accounts.view') || $u->can('settings.integrations.view')) { $pt[] = ['Providers & Merchant Accounts', route('admin.payments.providers'), request()->routeIs('admin.payments.providers')]; }
    if (\Illuminate\Support\Facades\Route::has('admin.payments.webhooks') && ($u->can('payments.webhooks.view') || $u->can('settings.payment-accounts.view'))) { $pt[] = ['Webhooks', route('admin.payments.webhooks'), request()->routeIs('admin.payments.webhooks*')]; }
    if ($u->can('settings.payment-accounts.view') || $u->can('payments.accounts.view')) { $pt[] = ['Payment Routing', route('admin.settings.section', 'payments'), request()->is('dashboard/settings/payments')]; }
@endphp
<nav class="a-tabs" aria-label="Payment settings" style="margin-top:-8px">
    @foreach($pt as [$label, $url, $active])
        <a href="{{ $url }}" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
