@extends('admin.master')
@section('title', 'Payment Methods')
@section('content')
<x-admin.page-header title="Payment Methods" subtitle="Methods customers can choose at checkout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings' => route('admin.settings.edit'), 'Payment Methods']">
    <a href="{{ route('admin.settings.edit') }}" class="a-btn a-btn--ghost">&larr; Back to General Settings</a>
    <a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Payment Method</a>
</x-admin.page-header>
<x-admin.flash />
<x-admin.card flush>
    @if($methods->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment methods found." text="Add a method to let customers pay.">
            <a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary">Add Payment Method</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Payment methods">
            <thead><tr><th>Name</th><th>Code</th><th>Checkout</th><th>Provider</th><th>Status</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($methods as $method)
                <tr>
                    <td data-label="Name"><span class="a-table__main">{{ $method->name }}</span></td>
                    <td data-label="Code"><code>{{ $method->code }}</code></td>
                    <td data-label="Checkout" class="a-wrap">@if($method->checkout_icon_url)<img class="a-m-icon" src="{{ $method->checkout_icon_url }}" alt="">@endif <span class="a-table__main" style="display:inline">{{ $method->checkout_label ?: $method->name }}</span>@if($method->checkout_description)<span class="a-table__sub">{{ $method->checkout_description }}</span>@endif</td>
                    <td data-label="Provider">{{ strtoupper($method->provider) }}</td>
                    <td data-label="Status"><x-admin.badge :variant="$method->is_active ? 'success' : 'default'" dot>{{ $method->is_active ? 'Active' : 'Disabled' }}</x-admin.badge></td>
                    <td data-label="Actions"><div class="a-table__actions">
                        <a href="{{ route('admin.payment-methods.edit', $method) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" data-a-confirm="Delete this payment method?" data-a-confirm-title="Delete payment method" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
    @endif
</x-admin.card>
@endsection
