<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
            'Download Link: '.$this->publicDownloadLink($ticket->ticket_number),
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
$this->publicDownloadLink((string) $ticket->ticket_number)
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
            throw new RuntimeException('UltraMsg is not configured. Please set ULTRAMSG_INSTANCE_ID and ULTRAMSG_TOKEN.');
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post($baseUrl.'/'.$instanceId.'/messages/chat', [
                'token' => $token,
                'to' => ltrim($phone, '+'),
                'body' => $body,
                'priority' => 10,
            ]);

        $response->throw();

        $payload = $response->json();
        $sent = data_get($payload, 'sent');

        if (! in_array($sent, [True, 'true', 1, '1'], true)) {
            throw new RuntimeException('UltraMsg accepted request but did not mark it as sent. Response: '.$response->body());
        }
    }

    private function publicDownloadLink(string $ticketNumber): string
    {
        return route('front.tickets.short-download', [
            'ticketNumber' => $ticketNumber,
        ]);
    }

    private function normalizePhone(string $phone): ?string
    {
        $normalized = preg_replace('/[^\d+]/', '', trim($phone));
        if (! $normalized) {
            return null;
        }

        $digitsOnly = preg_replace('/[^\d]/', '', $normalized);
        if (preg_match('/^01\d{9}$/', $digitsOnly)) {
            $normalized = '+20'.$digitsOnly;
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

