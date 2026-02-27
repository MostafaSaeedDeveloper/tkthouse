<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; padding: 24px; color: #111; }
        .ticket { border: 2px solid #111; border-radius: 12px; padding: 20px; }
        .qr { text-align: center; margin-top: 20px; }
        .meta p { margin: 5px 0; }
    </style>
</head>
<body>
<div class="ticket">
    <h2>Ticket {{ $ticket->ticket_number }}</h2>
    <div class="meta">
        <p><strong>Order:</strong> {{ $ticket->order->order_number }}</p>
        <p><strong>Holder:</strong> {{ $ticket->holder_name }}</p>
        <p><strong>Email:</strong> {{ $ticket->holder_email }}</p>
        <p><strong>Ticket Type:</strong> {{ $ticket->ticket_name }}</p>
        <p><strong>Price:</strong> {{ number_format((float) $ticket->ticket_price, 2) }}</p>
    </div>
    <div class="qr">
        <img src="{{ $ticket->qrUrl() }}" width="180" alt="qr">
    </div>
</div>
</body>
</html>
