@extends('admin.master')

@section('title', 'Promo Codes')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header title="Promo Codes" subtitle="Discount codes customers can apply at checkout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates' => route('admin.affiliates.index'), 'Promo Codes']">
    @can('affiliates.view')
        <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back to Affiliate</a>
    @endcan
    @can('promo-codes.create')
        <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Promo Code</a>
    @endcan
</x-admin.page-header>

<x-admin.flash />

@if($showStats)
<div class="a-grid a-grid--4" style="margin-bottom:12px">
    <x-admin.kpi label="Promo codes" :value="$totals['codes']" icon="fa-tags" :hint="$totals['active'].' active'" />
    <x-admin.kpi label="Redemptions" :value="number_format($totals['redemptions'])" icon="fa-ticket" hint="Times codes were used" />
    <x-admin.kpi label="Discount given" :value="number_format($totals['discount'], 2).' EGP'" icon="fa-percent" :hint="'On '.$totals['orders'].' paid orders'" />
    <x-admin.kpi label="Revenue with promo" :value="number_format($totals['revenue'], 2).' EGP'" icon="fa-sack-dollar" hint="Paid orders, after discount, before fees" />
</div>
@endif

<x-admin.card flush>
    @if($promoCodes->isEmpty())
        <x-admin.empty icon="fa-tags" title="No promo codes yet" text="Create a code to offer a percentage or fixed discount.">
            @can('promo-codes.create')
                <a href="{{ route('admin.promo-codes.create') }}" class="a-btn a-btn--primary a-btn--sm">Add Promo Code</a>
            @endcan
        </x-admin.empty>
    @else
    @php $showActions = auth()->user()->canany(['promo-codes.update', 'promo-codes.delete']); @endphp
    <x-admin.table stack caption="Promo codes">
        <thead><tr><th>Code</th><th>Discount</th><th>Usage</th>@if($showStats)<th>Paid orders</th><th>Discount given</th><th>Revenue</th>@endif<th>Status</th><th>Period</th>@if($showActions)<th class="a-num">Actions</th>@endif</tr></thead>
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
                @if($showStats)
                @php $sales = $perCode->get($promoCode->id); @endphp
                <td data-label="Paid orders">{{ (int) ($sales->orders ?? 0) }}</td>
                <td data-label="Discount given">{{ number_format((float) ($sales->discount ?? 0), 2) }} EGP</td>
                <td data-label="Revenue">{{ number_format((float) ($sales->revenue ?? 0), 2) }} EGP</td>
                @endif
                <td data-label="Status"><x-admin.badge :variant="$promoCode->is_active ? 'success' : 'default'" dot>{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                <td data-label="Period">{{ $promoCode->starts_at?->format('Y-m-d H:i') ?? 'Any' }} → {{ $promoCode->ends_at?->format('Y-m-d H:i') ?? 'Open' }}</td>
                @if($showActions)
                <td data-label="Actions"><div class="a-table__actions">
                    @can('promo-codes.update')
                        <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="a-btn a-btn--outline a-btn--sm"><i class="fa fa-pen"></i> Edit</a>
                    @endcan
                    @can('promo-codes.delete')
                        <form method="POST" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" data-a-confirm="Delete this promo code?" data-a-confirm-title="Delete promo code" data-a-confirm-ok="Delete">
                            @csrf
                            @method('DELETE')
                            <button class="a-btn a-btn--danger a-btn--sm" type="submit"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    @endcan
                </div></td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$promoCodes" />
    @endif
</x-admin.card>

@if($activities !== null)
<x-admin.card title="Recent activity" subtitle="Who created, changed or deleted promo codes." flush style="margin-top:16px">
    <x-slot:actions><a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.activity-logs.index', ['log' => 'promo_codes']) }}">View all</a></x-slot:actions>
    @if($activities->isEmpty())
        <x-admin.empty icon="fa-clock-rotate-left" title="No activity yet" text="Creating, editing or deleting a promo code shows up here." />
    @else
        <x-admin.table stack caption="Promo code activity">
            <thead><tr><th>Date</th><th>Who</th><th>What</th><th>Changes</th></tr></thead>
            <tbody>
            @foreach($activities as $activity)
                @php
                    $new = (array) ($activity->properties['attributes'] ?? []);
                    $old = (array) ($activity->properties['old'] ?? []);
                    $changes = collect($new)->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(array_key_exists($k, $old) ? ($old[$k] ?? '—').' → ' : '').(is_bool($v) ? ($v ? 'yes' : 'no') : ($v ?? '—')))->implode(', ');
                @endphp
                <tr>
                    <td data-label="Date">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                    <td data-label="Who">{{ $activity->causer?->name ?? 'System' }}</td>
                    <td data-label="What" class="a-wrap">{{ $activity->description }}</td>
                    <td data-label="Changes" class="a-wrap">{{ $activity->event === 'updated' ? $changes : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
    @endif
</x-admin.card>
@endif
@endsection
