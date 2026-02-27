<?php

namespace App\Services;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketIssuanceService
{
    public function issueIfPaid(Order $order): void
    {
        $order->unsetRelation('items');
        $order->unsetRelation('issuedTickets');
        $order->load(['items', 'customer']);

        if ($order->status !== 'complete' || $order->payment_status !== 'paid') {
            return;
        }

        foreach ($order->items as $item) {
            for ($seatIndex = 1; $seatIndex <= (int) $item->quantity; $seatIndex++) {
                $issuedTicket = IssuedTicket::firstOrCreate(
                    [
                        'order_item_id' => $item->id,
                        'seat_index' => $seatIndex,
                    ],
                    [
                        'order_id' => $order->id,
                        'uuid' => (string) Str::uuid(),
                        'ticket_number' => $this->ticketNumber($order->id, $item->id, $seatIndex),
                        'holder_name' => $item->holder_name,
                        'holder_email' => $item->holder_email,
                        'holder_phone' => $item->holder_phone,
                        'ticket_name' => $item->ticket_name,
                        'ticket_price' => $item->ticket_price,
                    ]
                );

                Ticket::firstOrCreate(
                    [
                        'ticket_number' => $issuedTicket->ticket_number,
                    ],
                    [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'name' => $item->ticket_name,
                        'price' => $item->ticket_price,
                        'description' => 'Issued from order #'.$order->order_number,
                        'status' => 'not_checked_in',
                        'holder_name' => $item->holder_name,
                        'holder_email' => $item->holder_email,
                        'holder_phone' => $item->holder_phone,
                        'qr_payload' => $issuedTicket->ticket_number,
                        'issued_at' => now(),
                    ]
                );
            }
        }

        if (! $order->tickets_generated_at) {
            $order->forceFill(['tickets_generated_at' => now()])->save();
        }

        if ($order->issuedTickets()->whereNull('sent_at')->doesntExist()) {
            return;
        }

        $order->load('issuedTickets');

        Mail::to($order->customer->email)->send(new OrderTicketsIssuedMail($order));
        $this->sendWhatsapp($order);

        $order->issuedTickets()->whereNull('sent_at')->update(['sent_at' => now()]);
    }

    private function ticketNumber(int $orderId, int $itemId, int $seatIndex): string
    {
        return sprintf('%06d%03d%02d', $orderId, $itemId, $seatIndex);
    }

    private function sendWhatsapp(Order $order): void
    {
        $url = config('services.whatsapp.webhook_url');
        if (! $url) {
            Log::info('WhatsApp webhook is not configured; skipped sending tickets.', ['order_id' => $order->id]);

            return;
        }

        $tickets = $order->issuedTickets->map(fn (IssuedTicket $ticket) => [
            'ticket_number' => $ticket->ticket_number,
            'show_url' => route('front.tickets.show', $ticket),
            'pdf_url' => route('front.tickets.download', $ticket),
        ])->values()->all();

        Http::timeout(15)
            ->withToken((string) config('services.whatsapp.token'))
            ->post($url, [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->full_name,
                'customer_phone' => $order->customer->phone,
                'customer_email' => $order->customer->email,
                'tickets' => $tickets,
            ])
            ->throw();
    }
}
