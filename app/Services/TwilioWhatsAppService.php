<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppService
{
    public function sendTicket(Ticket $ticket): bool
    {
        $ticket->loadMissing(['order.customer']);

        $toPhone = $this->normalizePhone($ticket->holder_phone ?: $ticket->order?->customer?->phone);
        if (! $toPhone) {
            return false;
        }

        $lines = [
            'Your ticket is ready ✅',
            'Ticket #: '.$ticket->ticket_number,
            'Name: '.($ticket->holder_name ?: '-'),
            'Event: '.($ticket->eventLabel() ?: '-'),
            'Type: '.($ticket->ticketTypeLabel() ?: '-'),
            'Ticket link: '.route('admin.tickets.show', $ticket),
            'PDF: '.route('admin.tickets.download', $ticket),
        ];

        return $this->sendMessage($toPhone, implode("\n", $lines));
    }

    public function sendOrderTickets(Order $order, Collection $tickets): bool
    {
        $order->loadMissing(['customer']);

        $sentAll = true;

        $groupedByPhone = $tickets
            ->filter(fn (IssuedTicket $ticket) => filled($ticket->holder_phone))
            ->groupBy(fn (IssuedTicket $ticket) => $this->normalizePhone($ticket->holder_phone));

        foreach ($groupedByPhone as $phone => $holderTickets) {
            if (! $phone) {
                continue;
            }

            $lines = [
                'Your booking has been paid successfully ✅',
                'Order #: '.$order->order_number,
                'Name: '.($holderTickets->first()->holder_name ?: $order->customer?->full_name ?: '-'),
                'Tickets:',
            ];

            foreach ($holderTickets->sortBy('ticket_number') as $ticket) {
                $lines[] = '- #'.$ticket->ticket_number.' | '.($ticket->ticket_name ?: '-');
                $lines[] = '  Link: '.route('front.tickets.show', $ticket);
            }

            $lines[] = 'Thank you for your purchase.';

            $sentAll = $this->sendMessage($phone, implode("\n", $lines)) && $sentAll;
        }

        $customerPhone = $this->normalizePhone($order->customer?->phone);
        if ($customerPhone && $groupedByPhone->keys()->doesntContain($customerPhone)) {
            $summary = [
                'Your booking has been paid successfully ✅',
                'Order #: '.$order->order_number,
                'Customer: '.($order->customer?->full_name ?: '-'),
                'Tickets count: '.$tickets->count(),
            ];

            $sentAll = $this->sendMessage($customerPhone, implode("\n", $summary)) && $sentAll;
        }

        return $sentAll;
    }

    private function sendMessage(string $toPhone, string $body): bool
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.whatsapp_from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            Log::warning('Twilio WhatsApp is not configured; skipped sending.', ['to' => $toPhone]);

            return false;
        }

        $response = Http::asForm()
            ->timeout(20)
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $this->asWhatsappAddress($from),
                'To' => $this->asWhatsappAddress($toPhone),
                'Body' => $body,
            ]);

        if (! $response->successful()) {
            Log::error('Twilio WhatsApp send failed.', [
                'to' => $toPhone,
                'status' => $response->status(),
                'response' => $response->json() ?: $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $raw) ?: '';

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (! str_starts_with($normalized, '+')) {
            $normalized = '+'.$normalized;
        }

        return $normalized;
    }

    private function asWhatsappAddress(string $phone): string
    {
        $trimmed = trim($phone);

        if (str_starts_with($trimmed, 'whatsapp:')) {
            return $trimmed;
        }

        return 'whatsapp:'.$trimmed;
    }
}

