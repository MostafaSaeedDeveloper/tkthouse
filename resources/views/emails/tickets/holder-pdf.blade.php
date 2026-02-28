<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        .logo { text-align:center; margin-bottom: 14px; }
        .logo img { width: 146px; height: auto; margin: 0 auto; }
        body { font-family: DejaVu Sans, sans-serif; color:#111; }
        .ticket { border: 1px solid #222; border-radius: 10px; padding: 14px; margin-bottom: 18px; page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="logo"><img src="{{ public_path('images/logo-light.png') }}" alt="TKT House"></div>
@foreach($tickets as $ticket)
    <div class="ticket">
        <h3>{{ $ticket->ticket_number }}</h3>
        <p><strong>Order:</strong> {{ $order->order_number }}</p>
        <p><strong>Holder:</strong> {{ $ticket->holder_name }}</p>
        <p><strong>Email:</strong> {{ $ticket->holder_email }}</p>
        <p><strong>Ticket:</strong> {{ $ticket->ticket_name }}</p>
        <div><img src="{{ $ticket->qrUrl() }}" width="130"></div>
    </div>
@endforeach
</body>
</html>
