@extends('admin.master')

@section('title', 'Promo Codes')


@section('content')
<x-admin.page-header title="Promo Codes" subtitle="Discount codes customers can apply at checkout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates' => route('admin.affiliates.index'), 'Promo Codes']">
    <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back to Affiliate</a>
    <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Promo Code</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.card flush>
    @if($promoCodes->isEmpty())
        <x-admin.empty icon="fa-tags" title="No promo codes yet" text="Create a code to offer a percentage or fixed discount.">
            <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary a-btn--sm">Add Promo Code</a>
        </x-admin.empty>
    @else
    <x-admin.table stack caption="Promo codes">
        <thead><tr><th>Code</th><th>Discount</th><th>Usage</th><th>Status</th><th>Period</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($promoCodes as $promoCode)
            <tr>
                <td data-label="Code"><span class="a-table__main">{{ $promoCode->code }}</span></td>
                <td data-label="Discount">
                    @if($promoCode->discount_type === 'percent')
                        {{ rtrim(rtrim(number_format((float) $promoCode->discount_value, 2), '0'), '.') }}%
                    @else
                        {{ number_format((float) $promoCode->discount_value, 2) }} EGP
                    @endif
                </td>
                <td data-label="Usage">{{ $promoCode->used_count }} / {{ $promoCode->usage_limit ?? '∞' }}</td>
                <td data-label="Status"><x-admin.badge :variant="$promoCode->is_active ? 'success' : 'default'" dot>{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                <td data-label="Period">{{ $promoCode->starts_at?->format('Y-m-d H:i') ?? 'Any' }} → {{ $promoCode->ends_at?->format('Y-m-d H:i') ?? 'Open' }}</td>
                <td data-label="Actions"><div class="a-table__actions">
                    <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="a-btn a-btn--outline a-btn--sm"><i class="fa fa-pen"></i> Edit</a>
                    <form method="POST" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" data-a-confirm="Delete this promo code?" data-a-confirm-title="Delete promo code" data-a-confirm-ok="Delete">
                        @csrf
                        @method('DELETE')
                        <button class="a-btn a-btn--danger a-btn--sm" type="submit"><i class="fa fa-trash"></i> Delete</button>
                    </form>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$promoCodes" />
    @endif
</x-admin.card>
@endsection
