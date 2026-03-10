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

        $caption = implode("\n", [
            '🎟️ Your ticket is ready',
            'Ticket Number: '.$ticket->ticket_number,
            'Download Link: '.$this->publicDownloadLink((string) $ticket->ticket_number),
        ]);

        $this->sendImageMessage(
            phone: $phone,
            imageUrl: $this->qrImageUrl((string) ($ticket->qr_payload ?: $ticket->ticket_number)),
            caption: $caption,
        );

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

        $this->sendTextMessage($phone, implode("\n", [
            '✅ Payment completed successfully',
            'Order Number: '.$order->order_number,
            'Tickets with QR are below 👇',
        ]));

        foreach ($order->issuedTickets as $ticket) {
            $this->sendImageMessage(
                phone: $phone,
                imageUrl: $this->qrImageUrl((string) $ticket->ticket_number),
                caption: implode("\n", [
                    '🎟️ Ticket: '.$ticket->ticket_number,
                    'Download Link: '.$this->publicDownloadLink((string) $ticket->ticket_number),
                ]),
            );
        }

        return true;
    }

    private function sendTextMessage(string $phone, string $body): void
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post($this->apiBasePath().'/messages/chat', [
                'token' => $this->apiToken(),
                'to' => ltrim($phone, '+'),
                'body' => $body,
                'priority' => 10,
            ]);

        $response->throw();

        $this->assertSent((array) $response->json(), $response->body());
    }

    private function sendImageMessage(string $phone, string $imageUrl, string $caption): void
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post($this->apiBasePath().'/messages/image', [
                'token' => $this->apiToken(),
                'to' => ltrim($phone, '+'),
                'image' => $imageUrl,
                'caption' => $caption,
                'priority' => 10,
            ]);

        $response->throw();

        $this->assertSent((array) $response->json(), $response->body());
    }

    private function assertSent(array $payload, string $rawBody): void
    {
        $sent = data_get($payload, 'sent');

        if (! in_array($sent, [true, 'true', 1, '1'], true)) {
            throw new RuntimeException('UltraMsg accepted request but did not mark it as sent. Response: '.$rawBody);
        }
    }

    private function apiBasePath(): string
    {
        $instanceId = (string) config('services.ultramsg.instance_id');
        $baseUrl = rtrim((string) config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');

        if ($instanceId === '') {
            throw new RuntimeException('UltraMsg is not configured. Please set ULTRAMSG_INSTANCE_ID and ULTRAMSG_TOKEN.');
        }

        return $baseUrl.'/'.$instanceId;
    }

    private function apiToken(): string
    {
        $token = (string) config('services.ultramsg.token');

        if ($token === '') {
            throw new RuntimeException('UltraMsg is not configured. Please set ULTRAMSG_INSTANCE_ID and ULTRAMSG_TOKEN.');
        }

        return $token;
    }

    private function qrImageUrl(string $qrPayload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data='.urlencode($qrPayload);
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
