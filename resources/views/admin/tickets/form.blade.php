<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Ticket Code</label><input class="form-control" name="ticket_code" value="{{ old('ticket_code', $ticket->ticket_code ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Ticket Name</label><input class="form-control" name="ticket_name" value="{{ old('ticket_name', $ticket->ticket_name ?? '') }}" required></div>

    <div class="col-md-4"><label class="form-label">Order</label><select class="form-select" name="order_id" required>@foreach($orders as $order)<option value="{{ $order->id }}" @selected(old('order_id', $ticket->order_id ?? '') == $order->id)>{{ $order->order_number }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Order Item</label><select class="form-select" name="order_item_id" required>@foreach($orderItems as $item)<option value="{{ $item->id }}" @selected(old('order_item_id', $ticket->order_item_id ?? '') == $item->id)>#{{ $item->id }} - {{ $item->ticket_name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Customer</label><select class="form-select" name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $ticket->customer_id ?? '') == $customer->id)>{{ $customer->full_name }}</option>@endforeach</select></div>

    <div class="col-md-4"><label class="form-label">Event</label><select class="form-select" name="event_id" required>@foreach($events as $event)<option value="{{ $event->id }}" @selected(old('event_id', $ticket->event_id ?? '') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Event Ticket</label><select class="form-select" name="event_ticket_id" required>@foreach($eventTickets as $eventTicket)<option value="{{ $eventTicket->id }}" @selected(old('event_ticket_id', $ticket->event_ticket_id ?? '') == $eventTicket->id)>{{ $eventTicket->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" value="{{ old('price', $ticket->price ?? 0) }}" required></div>

    <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status" required>@foreach(['active','used','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', $ticket->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Issued At</label><input type="datetime-local" class="form-control" name="issued_at" value="{{ old('issued_at', isset($ticket) && $ticket->issued_at ? $ticket->issued_at->format('Y-m-d\TH:i') : '') }}"></div>
    <div class="col-md-4"><label class="form-label">Used At</label><input type="datetime-local" class="form-control" name="used_at" value="{{ old('used_at', isset($ticket) && $ticket->used_at ? $ticket->used_at->format('Y-m-d\TH:i') : '') }}"></div>
</div>
