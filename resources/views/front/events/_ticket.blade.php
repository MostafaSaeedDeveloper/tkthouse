@php
    $isCouple = (bool) $ticket->is_couple;
    $maxQuantity = $isCouple ? 2 : max(1, (int) ($ticket->max_per_order ?? 10));
    $isTicketSoldOut = $ticket->status === 'sold_out';
@endphp
<article class="tkt-ticket {{ $unavailable ? 'is-unavailable tkt-ticket--compact' : '' }} {{ $extra ? 'is-extra' : '' }}"
         data-id="ticket-{{ $ticket->id }}"
         data-ticket="{{ $ticket->name }}"
         data-price="{{ number_format($ticket->price, 2, '.', '') }}"
         data-max-qty="{{ $maxQuantity }}"
         data-is-couple="{{ $isCouple ? 1 : 0 }}"
         data-disabled="{{ $unavailable ? 1 : 0 }}">
    <div class="tkt-ticket__body">
        <h3 class="tkt-ticket__name" data-clamp title="{{ $ticket->name }}">{{ $ticket->name }}</h3>
        @if(! $unavailable && $ticket->description)
            <p class="tkt-ticket__desc" data-clamp>{{ $ticket->description }}</p>
        @endif
        @unless($unavailable)
            <button type="button" class="tkt-ticket__more" data-clamp-toggle hidden aria-expanded="false">Read more</button>
            <div class="tkt-ticket__tags">
                @if($isCouple)
                    <x-tkt.badge variant="warning">Couple pass · 2 tickets</x-tkt.badge>
                @else
                    <span>Max {{ $maxQuantity }} per order</span>
                @endif
            </div>
        @endunless
    </div>
    <div class="tkt-ticket__buy">
        <div class="tkt-ticket__price"><b>{{ number_format($ticket->price, 2) }}</b> <span>EGP</span>@unless($unavailable)<small>per ticket</small>@endunless</div>
        @if($unavailable)
            <x-tkt.badge variant="danger">{{ $isTicketSoldOut ? 'Sold out' : 'Unavailable' }}</x-tkt.badge>
        @else
            <div class="tkt-stepper" role="group" aria-label="Quantity for {{ $ticket->name }}">
                <button type="button" data-step="-1" aria-label="Decrease quantity" disabled>−</button>
                <output aria-live="polite">0</output>
                <button type="button" data-step="1" aria-label="Increase quantity">+</button>
            </div>
            <div class="tkt-ticket__sub" data-ticket-sub hidden></div>
        @endif
    </div>
</article>
