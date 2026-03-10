<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UltramsgWhatsappService
{
    public function sendTicket(Ticket $ticket, ?string $phoneOverride = null): bool
    {
        $phone = $this->normalizePhone((string) ($phoneOverride ?? $ticket->holder_phone));
        if (! $phone) {
            Log::warning('Ticket WhatsApp skipped: holder phone is missing or invalid.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);

            return false;
        }

        $holderName = trim((string) ($ticket->holder_name ?: 'Ticket Holder'));
        $ticketType = method_exists($ticket, 'ticketTypeLabel')
            ? trim((string) $ticket->ticketTypeLabel())
            : trim((string) $ticket->name);

        $caption = implode("\n", [
            '🎟️ Your ticket PDF is ready',
            'Holder: '.$holderName,
            'Ticket Type: '.($ticketType !== '' ? $ticketType : 'General'),
            'Ticket Number: '.$ticket->ticket_number,
        ]);

        $shortLink = $this->shortDownloadLink((string) $ticket->ticket_number);

        try {
            $this->sendDocumentMessage(
                phone: $phone,
                documentUrl: $this->signedPdfDownloadLink((string) $ticket->ticket_number),
                filename: $this->ticketFilename((string) $ticket->ticket_number),
                caption: $caption,
            );
        } catch (Throwable $exception) {
            Log::warning('UltraMsg document send failed, fallback to chat link.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'error' => $exception->getMessage(),
            ]);

            $this->sendTextMessage($phone, $caption."\nDownload Link: ".$shortLink);
        }

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
            'Ticket PDFs are attached below 👇',
        ]));

        foreach ($order->issuedTickets as $ticket) {
            $holderName = trim((string) ($ticket->holder_name ?: 'Ticket Holder'));
            $ticketType = $this->ticketTypeFromName((string) $ticket->ticket_name);
            $caption = implode("\n", [
                '🎟️ Ticket PDF',
                'Holder: '.$holderName,
                'Ticket Type: '.$ticketType,
                'Ticket Number: '.$ticket->ticket_number,
            ]);

            $shortLink = $this->shortDownloadLink((string) $ticket->ticket_number);

            try {
                $this->sendDocumentMessage(
                    phone: $phone,
                    documentUrl: $this->signedPdfDownloadLink((string) $ticket->ticket_number),
                    filename: $this->ticketFilename((string) $ticket->ticket_number),
                    caption: $caption,
                );
            } catch (Throwable $exception) {
                Log::warning('UltraMsg order document send failed, fallback to chat link.', [
                    'order_id' => $order->id,
                    'ticket_number' => $ticket->ticket_number,
                    'error' => $exception->getMessage(),
                ]);

                $this->sendTextMessage($phone, $caption."\nDownload Link: ".$shortLink);
            }
        }

        return true;
    }

    private function sendTextMessage(string $phone, string $body): void
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post($this->apiBasePath().'/messages/chat', [
                'token' => $this->apiToken(),
                'to' => ltrim($phone, '+'),
                'body' => $body,
                'priority' => 10,
            ]);

        $response->throw();

        $this->assertSent((array) $response->json(), $response->body());
    }

    private function sendDocumentMessage(string $phone, string $documentUrl, string $filename, string $caption): void
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->apiBasePath().'/messages/document', [
                'token' => $this->apiToken(),
                'to' => ltrim($phone, '+'),
                'document' => $documentUrl,
                'filename' => $filename,
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

    private function signedPdfDownloadLink(string $ticketNumber): string
    {
        return URL::temporarySignedRoute(
            'front.tickets.public-download',
            now()->addDays(7),
            ['ticketNumber' => $ticketNumber]
        );
    }

    private function shortDownloadLink(string $ticketNumber): string
    {
        return route('front.tickets.short-download', ['ticketNumber' => $ticketNumber]);
    }

    private function ticketFilename(string $ticketNumber): string
    {
        return 'ticket-'.Str::of($ticketNumber)->replace(['/', '\\', ' '], '-').'.pdf';
    }

    private function ticketTypeFromName(string $ticketName): string
    {
        if (! str_contains($ticketName, ' - ')) {
            return trim($ticketName) !== '' ? trim($ticketName) : 'General';
        }

        $type = trim((string) Str::after($ticketName, ' - '));

        return $type !== '' ? $type : 'General';
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
