@extends('admin.master')
@section('title', 'Transaction '.($attempt->internal_reference ?: '#'.$attempt->id))
@php $money = fn ($n) => number_format((float) $n, 2).' '.($attempt->currency ?: 'EGP'); @endphp
@section('content')
<x-admin.page-header :title="'Transaction '.($attempt->internal_reference ?: '#'.$attempt->id)" :subtitle="'Order #'.$attempt->order->order_number" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions' => route('admin.payments.transactions'), $attempt->internal_reference ?: '#'.$attempt->id]">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.show', $attempt->order) }}">Open order</a>
    @if($canInquire)
        <form method="POST" action="{{ route('admin.payments.transactions.inquire', $attempt) }}" data-a-loading>@csrf<button class="a-btn a-btn--primary" type="submit"><i class="fa fa-rotate"></i> Check payment status</button></form>
    @endif
    @includeIf('admin.orders._refund-actions', ['attempt' => $attempt, 'order' => $attempt->order])
</x-admin.page-header>
<x-admin.flash />
@error('refund')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
@include('admin.payments._nav')
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
    <x-admin.card title="Status history" subtitle="Built from what TKT House recorded: session, webhooks, checks, settlement and refunds.">
        <ul style="list-style:none;margin:0;padding:0">@foreach($history as $h)<li style="display:flex;gap:12px;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--a-line)"><span>{{ $h['label'] }}@if($h['detail'])<span class="a-table__sub">{{ $h['detail'] }}</span>@endif</span><time class="a-table__sub" style="white-space:nowrap">{{ $h['at']?->format('d M Y, H:i') }}</time></li>@endforeach</ul>
    </x-admin.card>
    <x-admin.card flush title="Refund history" :subtitle="$refunds->count().' refund(s)'" style="margin-top:12px">
        @if($refunds->isEmpty())<p class="a-hint" style="padding:16px">No refund was requested for this payment.</p>
        @else<x-admin.table stack compact caption="Refunds"><thead><tr><th>Refund</th><th class="a-num">Amount</th><th>Mode</th><th>Status</th><th>Requested</th></tr></thead><tbody>
            @foreach($refunds as $r)<tr><td data-label="Refund"><a class="a-table__main" href="{{ route('admin.payments.refunds.show', $r) }}">{{ $r->internal_reference }}</a></td><td data-label="Amount" class="a-num">{{ $r->amount() }} {{ $r->currency }}</td><td data-label="Mode">{{ $r->mode === 'api' ? 'Provider API' : 'Manual' }} · {{ $r->type }}</td><td data-label="Status">@include('admin.payments._refund-status', ['refund' => $r])</td><td data-label="Requested">{{ $r->requested_at->format('d M Y, H:i') }}</td></tr>@endforeach
        </tbody></x-admin.table>@endif
    </x-admin.card>
    @if($siblings->isNotEmpty())
    <x-admin.card flush title="Other attempts on this order" :subtitle="'A retry never overwrites an earlier attempt'" style="margin-top:12px">
        <x-admin.table stack compact caption="Other attempts"><thead><tr><th>Payment</th><th>Provider</th><th>Status</th><th>Date</th></tr></thead><tbody>
            @foreach($siblings as $o)<tr><td data-label="Payment"><a class="a-table__main" href="{{ route('admin.payments.transactions.show', $o) }}">{{ $o->internal_reference ?: '#'.$o->id }}</a></td><td data-label="Provider">{{ ucfirst($o->provider) }}</td><td data-label="Status"><x-admin.status :status="$o->displayStatus()" kind="payment" /></td><td data-label="Date">{{ $o->created_at->format('d M Y, H:i') }}</td></tr>@endforeach
        </tbody></x-admin.table>
    </x-admin.card>
    @endif
  </div>
  <div>
    <x-admin.card title="Payment">
        <dl class="a-kv">
            <div><dt>Status</dt><dd><x-admin.status :status="$attempt->displayStatus()" kind="payment" /></dd></div>
            <div><dt>Amount</dt><dd>{{ $money($attempt->amount) }}</dd></div>
            <div><dt>Provider</dt><dd>{{ ucfirst($attempt->provider) }} · {{ $attempt->method_code }}</dd></div>
            @if($seeAccounts)<div><dt>Merchant account</dt><dd>{{ $attempt->integrationAccount?->name ?? 'Legacy configuration' }}</dd></div>@endif
            <div><dt>Created</dt><dd>{{ $attempt->created_at->format('d M Y, H:i') }}</dd></div>
            <div><dt>Paid at</dt><dd>{{ ($attempt->paid_at ?? $attempt->settled_at)?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Last checked</dt><dd>{{ $attempt->last_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
            @if($attempt->failure_reason)<div><dt>Failure reason</dt><dd>{{ $attempt->failure_reason }}</dd></div>@endif
            @if($attempt->status === 'initiated' && ($li = data_get($attempt->meta, 'last_inquiry')))
                <div><dt>Provider answer</dt><dd class="a-wrap">
                    {{ $li['message'] ?? 'Not confirmed as paid' }}
                    <span class="a-table__sub">{{ collect(['provider_status' => 'status', 'paid' => 'paid', 'paid_at' => 'paid_at', 'total' => 'total', 'currency' => 'currency', 'invoice_id' => 'invoice'])
                        ->filter(fn ($label, $k) => array_key_exists($k, $li))->map(fn ($label, $k) => $label.'='.$li[$k])->implode(' · ') }}</span>
                    @if(!empty($li['data_fields']))<span class="a-table__sub">fields: {{ $li['data_fields'] }}</span>@endif
                </dd></div>
            @endif
        </dl>
    </x-admin.card>
    @if($seeAccounts && $attempt->routing_snapshot)
    <x-admin.card title="Routing snapshot" subtitle="How the merchant account was chosen when this attempt was opened (never rewritten).">
        <dl class="a-kv">@foreach(['source' => 'Decision', 'account_name' => 'Account', 'environment' => 'Environment', 'reason' => 'Note', 'resolved_at' => 'Resolved at'] as $k => $l)@if(data_get($attempt->routing_snapshot, $k))<div><dt>{{ $l }}</dt><dd>{{ data_get($attempt->routing_snapshot, $k) }}</dd></div>@endif @endforeach</dl>
    </x-admin.card>
    @endif
    <x-admin.card title="What can be done here" style="margin-top:12px">
        <dl class="a-kv">
            <div><dt>Check status</dt><dd>{{ $canInquire ? 'Yes — asks '.ucfirst($attempt->provider).' with this payment\'s own merchant account' : 'Needs the inquiry permission' }}</dd></div>
            <div><dt>Refund</dt><dd>@if($capability->mode === 'api')Provider API (full or partial) @elseif($capability->mode === 'manual')Manual / external: {{ $capability->note }} @else Not available: {{ $capability->note }} @endif</dd></div>
            <div><dt>Void</dt><dd>@if($capability->canVoid)Available (card, same business day, from the Refund dialog)@elseif($attempt->provider === 'paymob')Not available now (card payments only, same business day, API refunds must be enabled)@else Not supported by {{ ucfirst($attempt->provider) }}@endif</dd></div>
            <div><dt>Cancel</dt><dd>Not supported by the provider API.</dd></div>
            <div><dt>Related order</dt><dd><a href="{{ route('admin.orders.show', $attempt->order) }}">#{{ $attempt->order->order_number }}</a> ({{ str_replace('_', ' ', $attempt->order->status) }})</dd></div>
        </dl>
        <p class="a-hint" style="margin:8px 0 0">References: @foreach(array_filter(['Payment' => $attempt->internal_reference, 'Provider transaction' => $attempt->transaction_id, 'Provider reference' => $attempt->provider_reference]) as $label => $v)<button type="button" class="a-btn a-btn--ghost a-btn--sm" data-copy-text="{{ $v }}" title="Copy {{ $label }}"><i class="fa fa-copy"></i> {{ $label }}</button> @endforeach</p>
    </x-admin.card>
    @if($external->isNotEmpty())
    <x-admin.card title="Provider records for this payment" subtitle="Looked up or imported from the provider; they never change this payment." style="margin-top:12px">
        @foreach($external as $e)<p style="margin:0 0 6px"><a href="{{ route('admin.payments.imported.show', $e) }}">{{ $e->provider_transaction_id }}</a> — {{ ucfirst(str_replace('_', ' ', $e->status)) }}@if($e->refunded_amount_minor) · {{ number_format($e->refunded_amount_minor / 100, 2) }} refunded at the provider @endif<span class="a-table__sub">{{ $e->verified_at ? 'verified '.$e->verified_at->diffForHumans() : 'unverified (CSV)' }}@if($e->fee_minor !== null) · commission {{ number_format($e->fee_minor / 100, 2) }}@endif</span></p>@endforeach
    </x-admin.card>
    @endif
  </div>
</div>
@endsection
@push('scripts')<script>document.querySelectorAll('[data-copy-text]').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copyText); var o = b.innerHTML; b.innerHTML = '<i class="fa fa-check"></i> Copied'; setTimeout(function () { b.innerHTML = o; }, 1200); }); });</script>@endpush
