@extends('admin.master')

@section('title', 'Promo Codes')


@section('content')
<x-admin.page-header title="Promo Codes" subtitle="Discount codes customers can apply at checkout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates' => route('admin.affiliates.index'), 'Promo Codes']">
    <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back to Affiliate</a>
    <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Promo Code</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.card flush title="All promo codes" :subtitle="$promoCodes->total().' total'">
    @if($promoCodes->isEmpty())
        <x-admin.empty icon="fa-tags" title="No promo codes yet" text="Create a code to offer a percentage or fixed discount.">
            <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary a-btn--sm">Add Promo Code</a>
        </x-admin.empty>
    @else
    <x-admin.table stack caption="Promo codes">
        <thead><tr><th>Code</th><th class="a-num">Discount</th><th class="a-num">Usage</th><th>Period</th><th>Status</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($promoCodes as $promoCode)
            <tr>
                <td data-label="Code"><span class="a-table__main a-m-code a-trunc" title="{{ $promoCode->code }}">{{ $promoCode->code }}</span></td>
                <td data-label="Discount" class="a-num">
                    @if($promoCode->discount_type === 'percent')
                        <b>{{ rtrim(rtrim(number_format((float) $promoCode->discount_value, 2), '0'), '.') }}%</b>
                    @else
                        <b>{{ number_format((float) $promoCode->discount_value, 2) }}</b> <span class="a-muted">EGP</span>
                    @endif
                </td>
                <td data-label="Usage" class="a-num">{{ $promoCode->used_count }} <span class="a-muted">/ {{ $promoCode->usage_limit ?? '∞' }}</span></td>
                <td data-label="Period">{{ $promoCode->starts_at?->format('d M Y') ?? 'Any time' }}<span class="a-table__sub">until {{ $promoCode->ends_at?->format('d M Y') ?? 'open' }}</span></td>
                <td data-label="Status"><x-admin.status :status="$promoCode->is_active ? 'active' : 'inactive'" kind="event" /></td>
                <td data-label=""><div class="a-table__actions">
                    <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                    <x-admin.dropdown label="" aria-label="More actions">
                        <form method="POST" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" data-a-confirm="Delete promo code {{ $promoCode->code }}?" data-a-confirm-title="Delete promo code" data-a-confirm-ok="Delete">
                            @csrf
                            @method('DELETE')
                            <button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </x-admin.dropdown>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$promoCodes" />
    @endif
</x-admin.card>
@endsection
