{{-- Orders with at least one CONFIRMED refund (the dashboard "Total Refunded" / "Refunded Orders" cards link here). Amounts come from refund records only. --}}
@php
    $dec = fn (int $m) => \App\Models\PaymentRefund::minorToDecimal($m);
@endphp
<x-admin.table stack class="a-reft" caption="Orders with confirmed refunds">
    <thead>
        <tr>
            <th scope="col" aria-sort="{{ $ariaSort('created_at') }}"><a class="a-ordt__sort" href="{{ $sortUrl('created_at') }}">Order <i class="fa {{ $sortIcon('created_at') }}" aria-hidden="true"></i></a></th>
            <th scope="col">Customer</th>
            <th scope="col" class="a-num" aria-sort="{{ $ariaSort('total_amount') }}"><a class="a-ordt__sort" href="{{ $sortUrl('total_amount') }}">Paid <i class="fa {{ $sortIcon('total_amount') }}" aria-hidden="true"></i></a></th>
            <th scope="col" class="a-num" aria-sort="{{ $ariaSort('refunded_amount') }}"><a class="a-ordt__sort" href="{{ $sortUrl('refunded_amount') }}">Refunded <i class="fa {{ $sortIcon('refunded_amount') }}" aria-hidden="true"></i></a></th>
            <th scope="col" class="a-num">Remaining</th>
            <th scope="col">Refund</th>
            <th scope="col">Payment method</th>
            <th scope="col" aria-sort="{{ $ariaSort('refund_date') }}"><a class="a-ordt__sort" href="{{ $sortUrl('refund_date') }}">Refund date <i class="fa {{ $sortIcon('refund_date') }}" aria-hidden="true"></i></a></th>
            <th scope="col">Reason</th>
            <th scope="col"><span class="a-sr-only">Links</span></th>
        </tr>
    </thead>
    <tbody>
    @foreach($orders as $order)
        @php
            $paidMinor = (int) round(((float) $order->total_amount) * 100);
            $refundedMinor = (int) ($order->refunded_minor ?? 0);
            $remaining = max(0, $paidMinor - $refundedMinor);
            $latest = $order->refunds->first();
            $methodLabel = $order->payment_method ? ($paymentMethodLabels[$order->payment_method] ?? ucwords(str_replace('_', ' ', $order->payment_method))) : '—';
            $customerName = $order->customer?->full_name ?: 'Unknown customer';
        @endphp
        <tr>
            <td data-label="Order"><a class="a-table__main" href="{{ route('admin.orders.show', $order) }}">#{{ \App\Support\OrderPresenter::number($order) }}</a><span class="a-table__sub">{{ $order->created_at?->format('d-m-Y') }}</span></td>
            <td data-label="Customer"><span class="a-table__main a-ord-ellipsis" title="{{ $customerName }}">{{ $customerName }}</span><span class="a-table__sub a-ord-ellipsis">{{ $order->customer?->email }}</span></td>
            <td data-label="Paid" class="a-num"><span class="a-table__main">{{ number_format((float) $order->total_amount, 2) }}</span><span class="a-table__sub">EGP</span></td>
            <td data-label="Refunded" class="a-num"><span class="a-table__main">{{ $dec($refundedMinor) }}</span><span class="a-table__sub">EGP · {{ $order->refunds->count() }} refund(s)</span></td>
            <td data-label="Remaining" class="a-num"><span class="a-table__main">{{ $dec($remaining) }}</span><span class="a-table__sub">EGP</span></td>
            <td data-label="Refund">@if($refundedMinor >= $paidMinor)<x-admin.badge variant="info" dot>Full</x-admin.badge>@else<x-admin.badge variant="warning" dot>Partial</x-admin.badge>@endif</td>
            <td data-label="Payment method">{{ $methodLabel }}</td>
            <td data-label="Refund date">{{ $order->last_refund_at ? \Illuminate\Support\Carbon::parse($order->last_refund_at)->format('d M Y, H:i') : '—' }}</td>
            <td data-label="Reason"><span class="a-ord-clamp" title="{{ $latest?->reason }}">{{ $latest?->reason ?? '—' }}</span></td>
            <td data-label=""><div class="a-table__actions">
                <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.orders.show', $order) }}" aria-label="View order {{ \App\Support\OrderPresenter::number($order) }}"><i class="fa fa-eye"></i><span class="a-ord-btn-text">Order</span></a>
                @if($latest && auth()->user()->can('payments.refunds.view'))<a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.payments.refunds.show', $latest) }}" aria-label="Refund details {{ $latest->internal_reference }}"><i class="fa fa-rotate-left"></i><span class="a-ord-btn-text">Refund</span></a>@endif
            </div></td>
        </tr>
    @endforeach
    </tbody>
</x-admin.table>
