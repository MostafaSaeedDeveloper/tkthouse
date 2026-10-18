@php $u = auth()->user(); $canImported = \App\Http\Controllers\Admin\TransactionController::canSeeAccounts($u) || $u->can('payments.transactions.import'); @endphp
@if($canImported)
<nav class="a-tabs" aria-label="Transaction source" style="margin-top:-8px">
    <a href="{{ route('admin.payments.transactions') }}" class="{{ request()->routeIs('admin.payments.transactions*') ? 'is-active' : '' }}">Created by TKT House</a>
    <a href="{{ route('admin.payments.imported') }}" class="{{ request()->routeIs('admin.payments.imported*') ? 'is-active' : '' }}">Imported from provider</a>
</nav>
@endif
