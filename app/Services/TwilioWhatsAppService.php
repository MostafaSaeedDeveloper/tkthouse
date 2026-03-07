<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TwilioWhatsAppService
{
    public function sendTicket(Ticket $ticket): bool
    {
        $ticket->loadMissing(['order.customer']);

        $toPhone = $this->normalizePhone($ticket->holder_phone ?: $ticket->order?->customer?->phone);
        if (! $toPhone) {
            Log::warning('Ticket WhatsApp skipped: missing holder/customer phone.', ['ticket_id' => $ticket->id]);

            return false;
        }

        $issuedTicket = IssuedTicket::query()
            ->where('ticket_number', $ticket->ticket_number)
            ->first();

        $lines = [
            'Your ticket is ready ✅',
            'Ticket #: '.$ticket->ticket_number,
            'Name: '.($ticket->holder_name ?: '-'),
            'Event: '.($ticket->eventLabel() ?: '-'),
            'Type: '.($ticket->ticketTypeLabel() ?: '-'),
        ];

        if ($issuedTicket) {
            $lines[] = 'View ticket: '.$this->signedShowUrl($issuedTicket);
            $lines[] = 'Download PDF: '.$this->signedDownloadUrl($issuedTicket);
        }

        return $this->sendMessage($toPhone, implode("\n", $lines));
    }

    public function sendOrderTickets(Order $order, Collection $tickets): bool
    {
        $order->loadMissing(['customer']);

        $sentAll = true;
        $normalizedCustomerPhone = $this->normalizePhone($order->customer?->phone);

        $groupedByPhone = $tickets
            ->filter(fn (IssuedTicket $ticket) => filled($this->normalizePhone($ticket->holder_phone)))
            ->groupBy(fn (IssuedTicket $ticket) => $this->normalizePhone($ticket->holder_phone));

        foreach ($groupedByPhone as $phone => $holderTickets) {
            $lines = [
                'Payment confirmed ✅',
                'Order #: '.$order->order_number,
                'Name: '.($holderTickets->first()->holder_name ?: $order->customer?->full_name ?: '-'),
                'Tickets:',
            ];

            foreach ($holderTickets->sortBy('ticket_number') as $ticket) {
                $lines[] = '- #'.$ticket->ticket_number.' | '.($ticket->ticket_name ?: '-');
                $lines[] = '  Link: '.$this->signedShowUrl($ticket);
            }

            $sentAll = $this->sendMessage($phone, implode("\n", $lines)) && $sentAll;
        }

        if ($normalizedCustomerPhone && $groupedByPhone->isEmpty()) {
            $lines = [
                'Payment confirmed ✅',
                'Order #: '.$order->order_number,
                'Customer: '.($order->customer?->full_name ?: '-'),
                'Tickets:',
            ];

            foreach ($tickets->sortBy('ticket_number') as $ticket) {
                $lines[] = '- #'.$ticket->ticket_number.' | '.($ticket->ticket_name ?: '-');
                $lines[] = '  Link: '.$this->signedShowUrl($ticket);
            }

            $sentAll = $this->sendMessage($normalizedCustomerPhone, implode("\n", $lines)) && $sentAll;
        }

        if ($normalizedCustomerPhone && $groupedByPhone->isNotEmpty() && $groupedByPhone->keys()->doesntContain($normalizedCustomerPhone)) {
            $summary = [
                'Payment confirmed ✅',
                'Order #: '.$order->order_number,
                'Tickets count: '.$tickets->count(),
            ];

            $sentAll = $this->sendMessage($normalizedCustomerPhone, implode("\n", $summary)) && $sentAll;
        }

        return $sentAll;
    }

    private function signedShowUrl(IssuedTicket $ticket): string
    {
        return URL::temporarySignedRoute('front.tickets.public.show', now()->addDays(30), ['ticket' => $ticket]);
    }

    private function signedDownloadUrl(IssuedTicket $ticket): string
    {
        return URL::temporarySignedRoute('front.tickets.public.download', now()->addDays(30), ['ticket' => $ticket]);
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

        Log::info('Twilio WhatsApp sent.', [
            'to' => $toPhone,
            'sid' => data_get($response->json(), 'sid'),
            'status' => data_get($response->json(), 'status'),
        ]);

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
