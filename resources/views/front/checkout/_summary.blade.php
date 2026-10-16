{{-- Order summary. $quote is computed on the server (CheckoutPricing). --}}
@php
    $thumb = $event->cover_image_url ?: $event->event_banner_url;
    $time = $event->event_time ? \Carbon\Carbon::parse($event->event_time)->format('g:i A') : null;
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
@endphp
<div class="tkt-card tkt-ordersum" id="orderSummaryCard">
    <div class="tkt-ordersum__event">
        <div class="tkt-media tkt-ordersum__thumb {{ $thumb ? '' : 'is-empty' }}">
            @if($thumb)<img class="tkt-img" src="{{ $thumb }}" alt="" loading="lazy">@endif
        </div>
        <div style="min-width:0">
            <div class="tkt-ordersum__event-name">{{ $event->name }}</div>
            <div class="tkt-ordersum__event-meta">
                {{ $event->formatted_date_range }}@if($time) · {{ $time }}@endif
                @if($event->location)<br>{{ $event->location }}@endif
            </div>
        </div>
    </div>

    <div class="tkt-ordersum__lines" data-sum-lines>
        @foreach($quote->lines as $line)
            <div class="tkt-ordersum__row">
                <span>{{ $line['name'] }} × {{ $line['count'] }}@if($line['is_couple'])<small>Couple pass</small>@endif</span>
                <span>{{ $money($line['total']) }}</span>
            </div>
        @endforeach
        <div class="tkt-ordersum__row tkt-ordersum__row--muted"><span>Attendees</span><span>{{ $quote->people }}</span></div>
    </div>

    <hr class="tkt-ordersum__rule">

    <div class="tkt-ordersum__lines">
        <div class="tkt-ordersum__row"><span>Subtotal</span><span data-sum-subtotal>{{ $money($quote->subtotal) }}</span></div>
        <div data-sum-fees style="display:contents">
            @foreach($quote->fees as $fee)
                <div class="tkt-ordersum__row tkt-ordersum__row--fee">
                    <span>{{ $fee['name'] }}@if($fee['fee_type'] === 'percentage')<small>{{ rtrim(rtrim(number_format($fee['value'], 2), '0'), '.') }}% of subtotal</small>@endif</span>
                    <span>{{ $money($fee['amount']) }}</span>
                </div>
            @endforeach
        </div>
        <div class="tkt-ordersum__row tkt-ordersum__row--discount" data-sum-discount-row @if($quote->discount <= 0) hidden @endif>
            <span>Discount <small data-sum-promo-code>{{ $quote->promo?->code }}</small></span>
            <span data-sum-discount>−{{ $money($quote->discount) }}</span>
        </div>
    </div>

    <hr class="tkt-ordersum__rule">

    <div class="tkt-ordersum__total"><span>Total</span><b data-sum-total>{{ $money($quote->total) }}</b></div>
    @if(count($quote->fees))
        <p class="tkt-ordersum__note" style="text-align:left;margin-top:6px">Booking fees are included in this total.</p>
    @endif
</div>
