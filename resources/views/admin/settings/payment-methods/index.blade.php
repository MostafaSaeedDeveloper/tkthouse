@extends('admin.master')
@section('title', 'Payment Methods')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Payment Methods" subtitle="What customers can choose at checkout. Which methods a customer actually sees depends on the merchant account that applies to their order." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings' => route('admin.settings.edit'), 'Payments']">
    @can('payment-methods.create')<a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Payment Method</a>@endcan
</x-admin.page-header>
<x-admin.flash />
@include('admin.settings._nav')
@include('admin.settings._payments-nav')
<x-admin.card flush>
    @if($methods->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment methods found." text="Add a method to let customers pay."><a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary">Add Payment Method</a></x-admin.empty>
    @else
        <x-admin.table stack compact caption="Payment methods">
            <thead><tr><th class="a-num">Order</th><th>Method</th><th>Code</th><th>Provider</th><th>Provider method ID</th><th>Status</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($methods as $method)
                <tr>
                    <td data-label="Order" class="a-num">{{ $method->sort_order }}</td>
                    <td data-label="Method" class="a-wrap">@if($method->checkout_icon_url)<img class="a-m-icon" src="{{ $method->checkout_icon_url }}" alt="">@endif <span class="a-table__main" style="display:inline">{{ $method->name }}</span>
                        <span class="a-table__sub">Customers see: {{ $method->checkout_label ?: $method->name }}</span>
                        </td>
                    <td data-label="Code"><code>{{ $method->code }}</code></td>
                    <td data-label="Provider">{{ strtoupper($method->provider) }}</td>
                    <td data-label="Provider method ID">{{ $method->providerMethodIdentifier() ?? '—' }}</td>
                    <td data-label="Status"><x-admin.badge :variant="$method->is_active ? 'success' : 'default'" dot>{{ $method->is_active ? 'Active' : 'Disabled' }}</x-admin.badge>@if($method->is_default)<x-admin.badge variant="warning">Default</x-admin.badge>@endif</td>
                    <td data-label="Actions"><div class="a-table__actions">
                        @can('payment-methods.update')
                            <form method="POST" action="{{ route('admin.payment-methods.default', $method) }}">@csrf
                                <button type="submit" class="a-btn a-btn--outline a-btn--sm" title="{{ $method->is_default ? 'Stop showing this method by default' : 'Show this method by default when no agent selection applies' }}" aria-pressed="{{ $method->is_default ? 'true' : 'false' }}"><i class="fa {{ $method->is_default ? 'fa-star' : 'fa-star-half-stroke' }}" aria-hidden="true"></i> {{ $method->is_default ? 'Remove default' : 'Set default' }}</button>
                            </form>
                            <a href="{{ route('admin.payment-methods.edit', $method) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        @endcan
                        @can('payment-methods.delete')
                            @unless(in_array($method->code, $used, true))<form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" data-a-confirm="Delete this payment method?" data-a-confirm-title="Delete payment method" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>@endunless
                        @endcan
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
    @endif
</x-admin.card>
@endsection
