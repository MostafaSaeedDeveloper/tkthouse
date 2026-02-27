<p>Hi {{ $order->customer->full_name ?: 'Customer' }},</p>
<p>Your order <strong>{{ $order->order_number }}</strong> is paid and your tickets are now generated.</p>
<ul>
@foreach($order->issuedTickets as $ticket)
    <li>
        {{ $ticket->ticket_number }} -
        <a href="{{ route('front.tickets.show', $ticket) }}">View</a>
        |
        <a href="{{ route('front.tickets.download', $ticket) }}">PDF</a>
    </li>
@endforeach
</ul>
<p>PDF attachment contains all tickets.</p>
