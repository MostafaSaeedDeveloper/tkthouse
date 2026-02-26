<p>مرحبًا {{ $ticket->order->customer_name ?? $ticket->order->user?->name }},</p>
<p>تم إصدار التذكرة بنجاح. الكود: {{ $ticket->code }}</p>
