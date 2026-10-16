@extends('admin.master')
@section('title', 'Transaction '.($attempt->internal_reference ?: '#'.$attempt->id))
@php $money = fn ($n) => number_format((float) $n, 2).' '.($attempt->currency ?: 'EGP'); @endphp
@section('content')
<x-admin.page-header :title="'Transaction '.($attempt->internal_reference ?: '#'.$attempt->id)" :subtitle="'Order #'.$attempt->order->order_number" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions' => route('admin.payments.transactions'), $attempt->internal_reference ?: '#'.$attempt->id]">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.show', $attempt->order) }}">Open order</a>
    @if($canInquire)
        <form method="POST" action="{{ route('admin.payments.transactions.inquire', $attempt) }}" data-a-loading>@csrf<button class="a-btn a-btn--primary" type="submit"><i class="fa fa-rotate"></i> Check payment status</button></form>
    @endif
</x-admin.page-header>
<x-admin.flash />
<div class="a-grid a-grid--main-side">
  <div>
    <x-admin.card title="References" subtitle="Our order number, our payment reference and the provider's identifiers are different things.">
        <dl class="a-kv">
            <div><dt>Order reference</dt><dd>#{{ $attempt->order->order_number }}</dd></div>
            <div><dt>Internal payment reference</dt><dd>{{ $attempt->internal_reference ?: 'Legacy attempt (none)' }}</dd></div>
            <div><dt>Merchant reference sent</dt><dd>{{ $attempt->reference ?: '—' }}</dd></div>
            <div><dt>Provider transaction / invoice ID</dt><dd>{{ $attempt->transaction_id ?: '—' }}</dd></div>
            <div><dt>Provider order / invoice key</dt><dd>{{ $attempt->provider_order_id ?: '—' }}</dd></div>
            <div><dt>Provider reference number</dt><dd>{{ $attempt->provider_reference ?: '—' }}</dd></div>
            @foreach(array_keys(array_filter((array) data_get($attempt->meta, 'previous_transaction_ids', []))) as $i)@endforeach
            @if(data_get($attempt->meta, 'previous_transaction_ids'))<div><dt>Earlier transaction IDs</dt><dd>{{ implode(', ', (array) data_get($attempt->meta, 'previous_transaction_ids')) }}</dd></div>@endif
        </dl>
    </x-admin.card>
    <x-admin.card flush title="Provider events" :subtitle="$attempt->webhookEvents->count().' received'">
        @if($attempt->webhookEvents->isEmpty())<p class="a-hint" style="padding:16px">No webhook was received for this attempt (redirect and reconciliation checks are not events).</p>
        @else
        <x-admin.table stack compact caption="Webhook events"><thead><tr><th>Received</th><th>Event</th><th>Signature</th><th>Processing</th></tr></thead><tbody>
            @foreach($attempt->webhookEvents as $e)<tr><td data-label="Received">{{ $e->received_at->format('d M Y, H:i:s') }}</td><td data-label="Event">{{ $e->event_type }}@if($e->deliveries > 1)<span class="a-table__sub">{{ $e->deliveries }} deliveries</span>@endif</td><td data-label="Signature"><x-admin.badge :variant="$e->signature_status === 'valid' ? 'success' : ($e->signature_status === 'invalid' ? 'danger' : 'warning')">{{ $e->signature_status }}</x-admin.badge></td><td data-label="Processing">{{ $e->processing_status }}@if($e->error)<span class="a-table__sub">{{ $e->error }}</span>@endif</td></tr>@endforeach
        </tbody></x-admin.table>@endif
    </x-admin.card>
  </div>
  <div>
    <x-admin.card title="Payment">
        <dl class="a-kv">
            <div><dt>Status</dt><dd><x-admin.status :status="$attempt->status" kind="payment" /></dd></div>
            <div><dt>Amount</dt><dd>{{ $money($attempt->amount) }}</dd></div>
            <div><dt>Provider</dt><dd>{{ ucfirst($attempt->provider) }} · {{ $attempt->method_code }}</dd></div>
            @if($seeAccounts)<div><dt>Merchant account</dt><dd>{{ $attempt->integrationAccount?->name ?? 'Legacy configuration' }}</dd></div>@endif
            <div><dt>Created</dt><dd>{{ $attempt->created_at->format('d M Y, H:i') }}</dd></div>
            <div><dt>Paid at</dt><dd>{{ ($attempt->paid_at ?? $attempt->settled_at)?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Last checked</dt><dd>{{ $attempt->last_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
            @if($attempt->failure_reason)<div><dt>Failure reason</dt><dd>{{ $attempt->failure_reason }}</dd></div>@endif
        </dl>
    </x-admin.card>
    @if($seeAccounts && $attempt->routing_snapshot)
    <x-admin.card title="Routing snapshot" subtitle="How the merchant account was chosen when this attempt was opened (never rewritten).">
        <dl class="a-kv">@foreach(['source' => 'Decision', 'account_name' => 'Account', 'environment' => 'Environment', 'reason' => 'Note', 'resolved_at' => 'Resolved at'] as $k => $l)@if(data_get($attempt->routing_snapshot, $k))<div><dt>{{ $l }}</dt><dd>{{ data_get($attempt->routing_snapshot, $k) }}</dd></div>@endif @endforeach</dl>
    </x-admin.card>
    @endif
  </div>
</div>
@endsection
