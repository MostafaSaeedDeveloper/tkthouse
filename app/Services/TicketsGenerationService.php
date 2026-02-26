<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketsGenerationService
{
    public function generate(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->loadMissing('items.ticketType.event');

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->qty; $i++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'event_id' => $item->ticketType->event_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'code' => 'TKT-'.strtoupper(Str::random(10)),
                        'qr_token' => Str::uuid()->toString().Str::random(24),
                        'issued_at' => now(),
                    ]);
                }

                $item->ticketType->increment('qty_sold', $item->qty);
            }
        });
    }
}
