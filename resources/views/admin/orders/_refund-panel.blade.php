{{-- Refund summary + history for an order. Financial facts come only from refund records; the order status is derived from them. --}}
@php
    $refunds = \App\Models\PaymentRefund::query()->where('order_id', $order->id)->with(['requester', 'approver', 'confirmer', 'ticketLinks.ticket'])->latest('id')->get();
    $paidMinor = (int) round(((float) $order->total_amount) * 100);
    $confirmedMinor = (int) $refunds->where('status', 'succeeded')->sum('amount_minor');
    $pendingMinor = (int) $refunds->whereIn('status', ['requested', 'processing', 'unknown'])->sum('amount_minor');
    $dec = fn (int $m) => \App\Models\PaymentRefund::minorToDecimal($m);
    $remainingMinor = max(0, $paidMinor - $confirmedMinor - $pendingMinor);
    $kind = $confirmedMinor <= 0 ? null : ($confirmedMinor >= $paidMinor ? 'Fully refunded' : 'Partially refunded');
@endphp
<x-admin.card flush title="Refunds" :subtitle="$refunds->count().' refund record(s) · only confirmed refunds change the order status and cancel tickets'">
    <div style="padding:16px">
        <dl class="a-kv">
            <div><dt>Refund status</dt><dd>@if($kind)<x-admin.badge variant="info" dot>{{ $kind }}</x-admin.badge>@elseif($pendingMinor > 0)<x-admin.badge variant="warning" dot>Refund pending</x-admin.badge>@else<span class="a-muted">No refunds</span>@endif</dd></div>
            <div><dt>Original paid amount</dt><dd>{{ $dec($paidMinor) }} EGP</dd></div>
            <div><dt>Confirmed refunded</dt><dd><strong>{{ $dec($confirmedMinor) }} EGP</strong></dd></div>
            <div><dt>Pending refund requests</dt><dd>{{ $dec($pendingMinor) }} EGP <span class="a-hint">(not money returned yet)</span></dd></div>
            <div><dt>Remaining refundable</dt><dd>{{ $dec($remainingMinor) }} EGP</dd></div>
            <div><dt>Net collected</dt><dd>{{ $dec(max(0, $paidMinor - $confirmedMinor)) }} EGP</dd></div>
        </dl>
    </div>
    @if($refunds->isEmpty())<p class="a-hint" style="padding:0 16px 16px">No refunds recorded for this order.</p>
    @else
    @foreach($refunds as $r)
        @php
            $canceledTickets = $r->ticketLinks->whereNotNull('canceled_at');
            $pendingTickets = $r->ticketLinks->whereNull('canceled_at');
        @endphp
        <div style="border-top:1px solid var(--a-line);padding:16px">
            <div class="a-btn-group" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
                <div><a class="a-table__main" href="{{ route('admin.payments.refunds.show', $r) }}">{{ $r->internal_reference }}</a>
                    <span class="a-table__sub">{{ $r->isFull() ? 'Full' : 'Partial' }} refund · {{ $r->amount() }} {{ $r->currency }}</span></div>
                @include('admin.payments._refund-status', ['refund' => $r])
            </div>
            <dl class="a-kv" style="margin-top:10px">
                <div><dt>Reason</dt><dd>{{ $r->reason }}</dd></div>
                <div><dt>Method</dt><dd>{{ $r->mode === 'api' ? 'Provider API ('.ucfirst($r->provider).')' : (\App\Models\PaymentRefund::EXTERNAL_METHODS[$r->external_method] ?? 'Manual / external') }}@if($r->isLegacyPayment()) · <span class="a-muted">legacy payment (no gateway transaction)</span>@endif</dd></div>
                <div><dt>Reference</dt><dd>{{ $r->provider_reference ?: ($r->provider_refund_id ?: '—') }}</dd></div>
                <div><dt>Refund date</dt><dd>{{ ($r->refunded_at ?? $r->completed_at)?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Requested by</dt><dd>{{ $r->requester?->name ?? '—' }} · {{ $r->requested_at?->format('d M Y, H:i') }}</dd></div>
                @if($r->approved_by)<div><dt>Approved by</dt><dd>{{ $r->approver?->name ?? '—' }} · {{ $r->approved_at?->format('d M Y, H:i') }}</dd></div>@endif
                @if($r->confirmed_by)<div><dt>Confirmed by</dt><dd>{{ $r->confirmer?->name ?? '—' }} · {{ $r->confirmed_at?->format('d M Y, H:i') }}</dd></div>@endif
                @if($r->internal_notes)<div><dt>Internal notes</dt><dd>{{ $r->internal_notes }}</dd></div>@endif
                <div><dt>Tickets</dt><dd>
                    @if($canceledTickets->isNotEmpty())<strong>{{ $canceledTickets->count() }} canceled:</strong> {{ $canceledTickets->map(fn ($l) => '#'.$l->ticket?->ticket_number.($l->was_checked_in ? ' (was checked in)' : ''))->implode(', ') }}
                    @elseif($pendingTickets->isNotEmpty())<span class="a-muted">{{ $pendingTickets->count() }} selected — canceled only when the refund is confirmed</span>
                    @elseif($r->effects_status === 'needs_review' || ($r->status === 'succeeded' && $r->effects_status === null))<x-admin.badge variant="warning">Needs ticket review</x-admin.badge> {{ $r->effects_note }}
                    @else<span class="a-muted">None (money only)</span>@endif
                </dd></div>
            </dl>
            <details style="margin-top:8px"><summary class="a-link a-text-sm">Refund activity</summary>
                <ul class="a-timeline" style="margin-top:8px">
                    @foreach(array_reverse($r->timeline()) as $t)
                        <li><strong>{{ str_replace('_', ' ', $t['event']) }}</strong> <span class="a-table__sub">{{ \Illuminate\Support\Carbon::parse($t['at'])->format('d M Y, H:i') }} · {{ $t['source'] ?? 'system' }}@isset($t['by']) · {{ $t['by'] }}@endisset</span>@isset($t['note'])<br><span>{{ $t['note'] }}</span>@endisset</li>
                    @endforeach
                </ul>
            </details>
        </div>
    @endforeach
    @endif
</x-admin.card>
