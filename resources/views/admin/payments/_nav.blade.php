@php $u = auth()->user(); @endphp
<nav class="a-tabs" aria-label="Payments" style="margin-top:-8px">
    @if($u->can('orders.view') || $u->can('payments.transactions.view'))<a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.index') ? 'is-active' : '' }}">Overview</a>@endif
    @can('payments.transactions.view')<a href="{{ route('admin.payments.transactions') }}" class="{{ request()->routeIs('admin.payments.transactions*') ? 'is-active' : '' }}">Transactions</a>@endcan
    @if(\Illuminate\Support\Facades\Route::has('admin.payments.refunds') && $u->can('payments.refunds.view'))<a href="{{ route('admin.payments.refunds') }}" class="{{ request()->routeIs('admin.payments.refunds*') ? 'is-active' : '' }}">Refunds</a>@endif
</nav>
