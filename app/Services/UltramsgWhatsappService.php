<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UltramsgWhatsappService
{
    public function sendTicket(Ticket $ticket): bool
    {
        $phone = $this->normalizePhone((string) $ticket->holder_phone);
        if (! $phone) {
            Log::warning('Ticket WhatsApp skipped: holder phone is missing or invalid.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);

            return false;
        }

        $body = implode("\n", [
            '🎟️ Your ticket is ready',
            'Ticket Number: '.$ticket->ticket_number,
            'Download Link: '.route('admin.tickets.download', $ticket),
        ]);

        $this->sendMessage($phone, $body);

        return true;
    }

    public function sendOrderTickets(Order $order): bool
    {
        $order->loadMissing(['customer', 'issuedTickets']);

        $phone = $this->normalizePhone((string) ($order->customer->phone ?? ''));
        if (! $phone) {
            Log::warning('Order WhatsApp skipped: customer phone is missing or invalid.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return false;
        }

        $lines = [
            '✅ Payment completed successfully',
            'Order Number: '.$order->order_number,
            'Your tickets download links:',
        ];

        foreach ($order->issuedTickets as $ticket) {
            $lines[] = sprintf(
                '- %s: %s',
                $ticket->ticket_number,
                route('front.tickets.download', $ticket)
            );
        }

        $this->sendMessage($phone, implode("\n", $lines));

        return true;
    }

    private function sendMessage(string $phone, string $body): void
    {
        $instanceId = (string) config('services.ultramsg.instance_id');
        $token = (string) config('services.ultramsg.token');
        $baseUrl = rtrim((string) config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');

        if ($instanceId === '' || $token === '') {
            throw new \RuntimeException('UltraMsg is not configured. Please set ULTRAMSG_INSTANCE_ID and ULTRAMSG_TOKEN.');
        }

        Http::asForm()
            ->timeout(15)
            ->post($baseUrl.'/'.$instanceId.'/messages/chat', [
                'token' => $token,
                'to' => $phone,
                'body' => $body,
            ])
            ->throw();
    }

    private function normalizePhone(string $phone): ?string
    {
        $normalized = preg_replace('/[^\d+]/', '', trim($phone));
        if (! $normalized) {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (! str_starts_with($normalized, '+')) {
            $normalized = '+'.$normalized;
        }

        return preg_match('/^\+\d{8,15}$/', $normalized) ? $normalized : null;
    }
}

