@php $u = auth()->user(); @endphp
<nav class="a-tabs" aria-label="Payments" style="margin-top:-8px">
    @if($u->can('orders.view') || $u->can('payments.transactions.view'))<a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.index') ? 'is-active' : '' }}">Overview</a>@endif
    @can('payments.transactions.view')<a href="{{ route('admin.payments.transactions') }}" class="{{ request()->routeIs('admin.payments.transactions*') ? 'is-active' : '' }}">Transactions</a>@endcan
    @if(\Illuminate\Support\Facades\Route::has('admin.payments.refunds') && $u->can('payments.refunds.view'))<a href="{{ route('admin.payments.refunds') }}" class="{{ request()->routeIs('admin.payments.refunds*') ? 'is-active' : '' }}">Refunds</a>@endif
    @if(\Illuminate\Support\Facades\Route::has('admin.payments.providers') && ($u->can('payments.accounts.view') || $u->can('settings.integrations.view')))<a href="{{ route('admin.payments.providers') }}" class="{{ request()->routeIs('admin.payments.providers') ? 'is-active' : '' }}">Providers &amp; Accounts</a>@endif
    @if(\Illuminate\Support\Facades\Route::has('admin.payments.webhooks') && ($u->can('payments.webhooks.view') || $u->can('settings.payment-accounts.view')))<a href="{{ route('admin.payments.webhooks') }}" class="{{ request()->routeIs('admin.payments.webhooks*') ? 'is-active' : '' }}">Webhooks</a>@endif
</nav>
