<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; padding: 24px; background: #f7f7f7; }
        .ticket { border: 2px dashed #111; background: #fff; border-radius: 16px; padding: 24px; }
        .title { font-size: 28px; margin: 0 0 8px; }
        .meta p { margin: 6px 0; font-size: 13px; }
        .qr { text-align: center; margin-top: 16px; }
    </style>
</head>
<body>
<div class="ticket">
    <h1 class="title">TKT HOUSE TICKET</h1>
    <div class="meta">
        <p><strong>Ticket Number:</strong> {{ $ticket->ticket_number }}</p>
        <p><strong>Order Number:</strong> {{ $ticket->order?->order_number ?? '-' }}</p>
        <p><strong>Name:</strong> {{ $ticket->holder_name ?: '-' }}</p>
        <p><strong>Email:</strong> {{ $ticket->holder_email ?: '-' }}</p>
        <p><strong>Status:</strong> {{ str($ticket->status)->replace('_',' ')->title() }}</p>
        <p><strong>Ticket Type:</strong> {{ $ticket->name }}</p>
    </div>
    <div class="qr">
        <img src="{{ $qrDataUri }}" alt="QR">
    </div>
</div>
</body>
</html>
