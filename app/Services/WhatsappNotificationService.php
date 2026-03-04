<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationService
{
    public function sendOrderTickets(Order $order): bool
    {
        $order->loadMissing(['issuedTickets', 'customer']);

        $phone = $this->formatTwilioWhatsappAddress($order->customer?->phone);
        if (! $phone) {
            Log::info('Skipping WhatsApp order notification: missing customer phone.', ['order_id' => $order->id]);

            return false;
        }

        $body = $this->buildOrderMessage($order);

        return $this->dispatchMessage($phone, $body, [
            'context' => 'order_paid',
            'order_id' => $order->id,
        ]);
    }

    public function sendSingleTicket(Ticket $ticket): bool
    {
        $phone = $this->formatTwilioWhatsappAddress($ticket->holder_phone);
        if (! $phone) {
            Log::info('Skipping WhatsApp ticket notification: missing holder phone.', ['ticket_id' => $ticket->id]);

            return false;
        }

        $body = implode("\n", [
            '🎫 Your ticket is ready.',
            'Ticket #: '.$ticket->ticket_number,
            'View ticket: '.route('admin.tickets.show', $ticket),
            'Download PDF: '.route('admin.tickets.download', $ticket),
        ]);

        return $this->dispatchMessage($phone, $body, [
            'context' => 'single_ticket',
            'ticket_id' => $ticket->id,
        ]);
    }

    private function dispatchMessage(string $to, string $body, array $context = []): bool
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.auth_token');
        $from = $this->formatTwilioWhatsappAddress(config('services.twilio.whatsapp_from'));

        if (! $sid || ! $token || ! $from) {
            Log::info('Skipping WhatsApp notification: Twilio is not fully configured.', $context);

            return false;
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid);

        Http::asForm()
            ->timeout(15)
            ->withBasicAuth($sid, $token)
            ->post($url, [
                'From' => $from,
                'To' => $to,
                'Body' => $body,
            ])
            ->throw();

        return true;
    }

    private function formatTwilioWhatsappAddress(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'whatsapp:')) {
            return $value;
        }

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (! str_starts_with($value, '+')) {
            $value = '+'.$value;
        }

        return 'whatsapp:'.$value;
    }

    private function buildOrderMessage(Order $order): string
    {
        $lines = [
            '✅ Payment confirmed for order #'.$order->order_number,
            'Your tickets are ready:',
        ];

        foreach ($order->issuedTickets as $ticket) {
            if (! $ticket instanceof IssuedTicket) {
                continue;
            }

            $lines[] = sprintf(
                '- %s | View: %s | PDF: %s',
                $ticket->ticket_number,
                route('front.tickets.show', $ticket),
                route('front.tickets.download', $ticket)
            );
        }

        return implode("\n", $lines);
    }
}
