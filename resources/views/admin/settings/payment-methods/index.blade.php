@extends('admin.master')
@section('title', 'Payment Methods')
@section('content')
<x-admin.page-header title="Payment Methods" subtitle="Methods customers can choose at checkout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings' => route('admin.settings.edit'), 'Payment Methods']">
    <a href="{{ route('admin.settings.edit') }}" class="a-btn a-btn--ghost">&larr; Back to General Settings</a>
    <a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Payment Method</a>
</x-admin.page-header>
<x-admin.flash />
<x-admin.card flush title="Checkout methods" :subtitle="$methods->count().' total'">
    @if($methods->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment methods found" text="Add a method to let customers pay.">
            <a href="{{ route('admin.payment-methods.create') }}" class="a-btn a-btn--primary">Add Payment Method</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Payment methods">
            <thead><tr><th>Method</th><th>Checkout</th><th>Provider</th><th>Status</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($methods as $method)
                <tr>
                    <td data-label="Method"><span class="a-table__main a-trunc">{{ $method->name }}</span><span class="a-table__sub a-m-mono">{{ $method->code }}</span></td>
                    <td data-label="Checkout"><div class="a-cell-media">
                        @if($method->checkout_icon_url)<img class="a-m-icon" src="{{ $method->checkout_icon_url }}" alt="">@endif
                        <div class="a-m-min0"><span class="a-table__main a-trunc">{{ $method->checkout_label ?: $method->name }}</span>@if($method->checkout_description)<span class="a-table__sub a-trunc a-m-trunc-lg" title="{{ $method->checkout_description }}">{{ $method->checkout_description }}</span>@endif</div>
                    </div></td>
                    <td data-label="Provider"><x-admin.badge>{{ strtoupper($method->provider) }}</x-admin.badge></td>
                    <td data-label="Status"><x-admin.status :status="$method->is_active ? 'active' : 'inactive'" kind="event" /></td>
                    <td data-label=""><div class="a-table__actions">
                        <a href="{{ route('admin.payment-methods.edit', $method) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        <x-admin.dropdown label="" aria-label="More actions">
                            <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" data-a-confirm="Delete this payment method?" data-a-confirm-title="Delete payment method" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button></form>
                        </x-admin.dropdown>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
    @endif
</x-admin.card>
@endsection
