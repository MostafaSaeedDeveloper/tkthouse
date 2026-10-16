<?php

namespace App\Services;

use App\Services\UltramsgWhatsappService;
use App\Mail\HolderTicketsIssuedMail;
use App\Mail\OrderInvoicePaidMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

class TicketIssuanceService
{
    /**
     * Issues the tickets of a paid order exactly once. Safe to call from callbacks, redirects, the
     * reconciliation job and admin saves at the same time: a per-order lock serialises them and
     * unsent tickets are claimed atomically before any mail/WhatsApp goes out.
     */
    public function issueIfPaid(Order $order): void
    {
        $lock = Cache::lock('issue-tickets:'.$order->id, 120);

        try {
            $lock->block(20);
        } catch (LockTimeoutException) {
            Log::warning('Ticket issuance skipped: another process is issuing this order.', ['order_id' => $order->id]);

            return;
        }

        try {
            $this->issue($order);
        } finally {
            $lock->release();
        }
    }

    /** True when a paid order has all its tickets and every delivery that can succeed has succeeded. */
    public function isComplete(Order $order): bool
    {
        if ($order->status !== 'paid' || ! $order->tickets_generated_at) {
            return false;
        }

        $order->loadMissing('items', 'customer');
        $expected = (int) $order->items->sum('quantity');
        $issued = $order->issuedTickets()->count();

        if ($issued < $expected) {
            return false;
        }

        if ($order->issuedTickets()->whereNull('sent_at')->exists()) {
            return false;
        }

        if (filled($order->customer?->email)) {
            foreach (['order_tickets_issued', 'order_invoice_paid'] as $key) {
                $sent = Activity::query()->where('subject_type', Order::class)->where('subject_id', $order->id)
                    ->where('log_name', 'order_delivery')->where('description', 'Delivery sent: '.$key)->exists();
                if (! $sent) {
                    return false;
                }
            }
        }

        return true;
    }

    private function issue(Order $order): void
    {
        $order->unsetRelation('items');
        $order->unsetRelation('issuedTickets');
        $order->load(['items', 'customer']);

        if ($order->status !== 'paid') {
            return;
        }

        // All-or-nothing: a failure half way leaves no partial set of tickets (and a retry builds the full set).
        DB::transaction(function () use ($order) {
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
        });

        if (! $order->tickets_generated_at) {
            $order->forceFill(['tickets_generated_at' => now()])->saveQuietly();
        }

        $this->deliver($order);
    }

    /**
     * Delivery is at-least-once and never double-sends what already succeeded: holder tickets are marked
     * `sent_at` only after their mail went out, and the customer-level mails / WhatsApp are recorded in the
     * activity log once delivered. A failed channel is simply retried by `orders:retry-ticket-issuance`.
     * Runs under the per-order lock, so two workers can never deliver the same thing in parallel.
     */
    private function deliver(Order $order): void
    {
        $order->load('issuedTickets');

        $pending = $order->issuedTickets->whereNull('sent_at');

        // Tickets without a holder e-mail have nothing to deliver to the holder.
        $noEmail = $pending->filter(fn (IssuedTicket $t) => blank($t->holder_email));
        if ($noEmail->isNotEmpty()) {
            IssuedTicket::query()->whereIn('id', $noEmail->pluck('id'))->update(['sent_at' => now()]);
        }

        $groupedByHolder = $pending
            ->filter(fn (IssuedTicket $ticket) => filled($ticket->holder_email))
            ->groupBy(fn (IssuedTicket $ticket) => mb_strtolower(trim((string) $ticket->holder_email)));

        foreach ($groupedByHolder as $holderEmail => $tickets) {
            $ok = $this->sendMailWithRetry(
                fn () => Mail::to($holderEmail)->send(new HolderTicketsIssuedMail($order, $tickets->values(), $holderEmail)),
                ['order_id' => $order->id, 'recipient' => $holderEmail, 'mail_type' => 'holder_tickets_issued']
            );

            if ($ok) {
                IssuedTicket::query()->whereIn('id', $tickets->pluck('id'))->update(['sent_at' => now()]);
            }
        }

        if (filled($order->customer?->email)) {
            $this->deliverOnce($order, 'order_tickets_issued', fn () => $this->sendMailWithRetry(
                fn () => Mail::to($order->customer->email)->send(new OrderTicketsIssuedMail($order)),
                ['order_id' => $order->id, 'recipient' => $order->customer->email, 'mail_type' => 'order_tickets_issued']
            ));

            $this->deliverOnce($order, 'order_invoice_paid', fn () => $this->sendMailWithRetry(
                fn () => Mail::to($order->customer->email)->send(new OrderInvoicePaidMail($order)),
                ['order_id' => $order->id, 'recipient' => $order->customer->email, 'mail_type' => 'order_invoice_paid']
            ));
        }

        // WhatsApp is best-effort and attempted once per order (the provider returns false when it is disabled/skipped).
        $this->deliverOnce($order, 'order_whatsapp', fn () => $this->sendOrderWhatsapp($order));
    }

    /** Runs $send unless it already succeeded for this order; records success so a retry does not repeat it. */
    private function deliverOnce(Order $order, string $key, callable $send): void
    {
        $description = 'Delivery sent: '.$key;

        $already = Activity::query()
            ->where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('log_name', 'order_delivery')
            ->where('description', $description)
            ->exists();

        if ($already) {
            return;
        }

        if ($send()) {
            activity('order_delivery')->performedOn($order)->log($description);
        }
    }

    private function ticketNumber(int $orderId, int $itemId, int $seatIndex): string
    {
        return sprintf('%06d%03d%02d', $orderId, $itemId, $seatIndex);
    }

    private function sendOrderWhatsapp(Order $order): bool
    {
        try {
            return (bool) app(UltramsgWhatsappService::class)->sendOrderTickets($order);
        } catch (Throwable $exception) {
            Log::error('Order WhatsApp send failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendMailWithRetry(callable $send, array $context = []): bool
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $send();

                return true;
            } catch (UnexpectedResponseException $exception) {
                $isRateLimited = $exception->getCode() === 550
                    && str_contains(mb_strtolower($exception->getMessage()), 'too many emails per second');

                if (! $isRateLimited || $attempt === $maxAttempts) {
                    Log::error('Mail send failed.', [...$context, 'attempt' => $attempt, 'error' => $exception->getMessage()]);

                    return false;
                }

                usleep($attempt * 500000);
            } catch (Throwable $exception) {
                Log::error('Mail send failed.', [...$context, 'attempt' => $attempt, 'error' => $exception->getMessage()]);

                return false;
            }
        }

        return false;
    }
}
