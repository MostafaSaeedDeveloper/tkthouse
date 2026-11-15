{{-- A ticket QR that stays hidden until the customer reveals it (or the event is close). Driven by public/js/tkt-qr.js.
     The image URL is only put into src when the QR is shown. --}}
@props(['ticket', 'shown' => false, 'large' => false])
<div class="tkt-qrbox {{ $large ? 'tkt-qrbox--lg' : '' }} {{ $shown ? '' : 'is-hidden' }}" data-qr-box data-qr-src="{{ $ticket->qrUrl() }}">
    <img data-qr-img @if($shown) src="{{ $ticket->qrUrl() }}" @endif alt="QR code for ticket {{ $ticket->ticket_number }}. Show it at the entrance." @if(! $large) width="96" height="96" @endif>
    <span class="tkt-qrbox__mask" aria-hidden="true">QR hidden</span>
</div>
