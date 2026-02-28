<p>Hello,</p>
<p>Your tickets for order <strong>#{{ $order->order_number }}</strong> have been issued successfully.</p>
<ul>
@foreach($tickets as $ticket)
    <li>
        {{ $ticket->ticket_number }} -
        <a href="{{ route('front.tickets.show', $ticket) }}">View ticket</a>
    </li>
@endforeach
</ul>
<p>A PDF attachment containing only your tickets is included.</p>
