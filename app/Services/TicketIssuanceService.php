<?php

namespace App\Services;

use App\Mail\HolderTicketsIssuedMail;
use App\Mail\OrderInvoicePaidMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

class TicketIssuanceService
{
    public function issueIfPaid(Order $order): void
    {
        $order->unsetRelation('items');
        $order->unsetRelation('issuedTickets');
        $order->load(['items', 'customer']);

        if ($order->status !== 'paid') {
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

        $groupedByHolder = $order->issuedTickets
            ->whereNull('sent_at')
            ->filter(fn (IssuedTicket $ticket) => filled($ticket->holder_email))
            ->groupBy(fn (IssuedTicket $ticket) => mb_strtolower(trim((string) $ticket->holder_email)));

        foreach ($groupedByHolder as $holderEmail => $tickets) {
            $this->sendMailWithRetry(
                fn () => Mail::to($holderEmail)->send(new HolderTicketsIssuedMail($order, $tickets->values(), $holderEmail)),
                ['order_id' => $order->id, 'recipient' => $holderEmail, 'mail_type' => 'holder_tickets_issued']
            );
        }

        if (filled($order->customer?->email)) {
            $this->sendMailWithRetry(
                fn () => Mail::to($order->customer->email)->send(new OrderTicketsIssuedMail($order)),
                ['order_id' => $order->id, 'recipient' => $order->customer->email, 'mail_type' => 'order_tickets_issued']
            );
        }

        $this->sendMailWithRetry(
            fn () => Mail::to($order->customer->email)->send(new OrderInvoicePaidMail($order)),
            ['order_id' => $order->id, 'recipient' => $order->customer->email, 'mail_type' => 'order_invoice_paid']
        );
        $this->sendWhatsapp($order, app(WhatsappSenderService::class));

        $order->issuedTickets()->whereNull('sent_at')->update(['sent_at' => now()]);
    }

    private function ticketNumber(int $orderId, int $itemId, int $seatIndex): string
    {
        return sprintf('%06d%03d%02d', $orderId, $itemId, $seatIndex);
    }

    private function sendWhatsapp(Order $order, WhatsappSenderService $whatsappSender): void
    {
        if (! $whatsappSender->isEnabled()) {
            Log::info('WhatsApp (WaSenderAPI) is not configured; skipped sending tickets.', ['order_id' => $order->id]);

            return;
        }

        $phoneGroups = $order->issuedTickets
            ->whereNull('sent_at')
            ->filter(fn (IssuedTicket $ticket) => filled($ticket->holder_phone))
            ->groupBy(fn (IssuedTicket $ticket) => trim((string) $ticket->holder_phone));

        foreach ($phoneGroups as $phone => $tickets) {
            $lines = [
                '✅ Payment confirmed for order #'.$order->order_number,
                '🎟️ Your tickets:',
            ];

            foreach ($tickets->values() as $ticket) {
                $lines[] = '- #'.$ticket->ticket_number;
                $lines[] = '  '.route('front.tickets.show', $ticket);
            }

            $message = implode("\n", $lines);

            try {
                $whatsappSender->sendMessage((string) $phone, $message);
            } catch (Throwable $exception) {
                Log::error('WhatsApp send failed.', [
                    'order_id' => $order->id,
                    'recipient' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function sendMailWithRetry(callable $send, array $context = []): void
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $send();

                return;
            } catch (UnexpectedResponseException $exception) {
                $isRateLimited = $exception->getCode() === 550
                    && str_contains(mb_strtolower($exception->getMessage()), 'too many emails per second');

                if (! $isRateLimited || $attempt === $maxAttempts) {
                    Log::error('Mail send failed.', [
                        ...$context,
                        'attempt' => $attempt,
                        'error' => $exception->getMessage(),
                    ]);

                    return;
                }

                usleep($attempt * 500000);
            } catch (Throwable $exception) {
                Log::error('Mail send failed.', [
                    ...$context,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }
        }
    }
}
