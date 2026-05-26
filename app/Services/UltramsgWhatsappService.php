<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Ticket;
use App\Support\SystemSettings;
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
        $subject = 'Ticket #'.$ticket->ticket_number;

        if (! $this->isTicketWhatsappSendingEnabled()) {
            Log::info('Ticket WhatsApp skipped: disabled from system settings.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);
            NotificationLog::logWhatsapp([
                'type' => 'ticket_sent',
                'recipient' => $phoneOverride ?? $ticket->holder_phone,
                'ticket_id' => $ticket->id,
                'subject' => $subject,
                'status' => 'skipped',
                'error' => 'WhatsApp sending disabled',
            ]);

            return false;
        }

        $phone = $this->normalizePhone((string) ($phoneOverride ?? $ticket->holder_phone));
        if (! $phone) {
            Log::warning('Ticket WhatsApp skipped: holder phone is missing or invalid.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);
            NotificationLog::logWhatsapp([
                'type' => 'ticket_sent',
                'recipient' => $phoneOverride ?? $ticket->holder_phone,
                'ticket_id' => $ticket->id,
                'subject' => $subject,
                'status' => 'skipped',
                'error' => 'Phone missing or invalid',
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
            $msgId = $this->sendDocumentWithFallbacks(
                phone: $phone,
                ticketNumber: (string) $ticket->ticket_number,
                caption: $caption,
                fallbackText: $caption."\nDownload Link: ".$shortLink,
                context: [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ],
            );
            NotificationLog::logWhatsapp([
                'type' => 'ticket_sent',
                'recipient' => $phone,
                'ticket_id' => $ticket->id,
                'subject' => $subject,
                'status' => 'sent',
                'message_id' => $msgId,
            ]);
        } catch (Throwable $exception) {
            NotificationLog::logWhatsapp([
                'type' => 'ticket_sent',
                'recipient' => $phone,
                'ticket_id' => $ticket->id,
                'subject' => $subject,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        return true;
    }

    public function sendOrderTickets(Order $order): bool
    {
        $orderSubject = 'Order #'.$order->order_number;

        if (! $this->isTicketWhatsappSendingEnabled()) {
            Log::info('Order WhatsApp skipped: disabled from system settings.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            NotificationLog::logWhatsapp([
                'type' => 'order_tickets',
                'recipient' => $order->customer->phone ?? null,
                'order_id' => $order->id,
                'subject' => $orderSubject,
                'status' => 'skipped',
                'error' => 'WhatsApp sending disabled',
            ]);

            return false;
        }

        $order->loadMissing(['customer', 'issuedTickets']);

        $phone = $this->normalizePhone((string) ($order->customer->phone ?? ''));
        if (! $phone) {
            Log::warning('Order WhatsApp skipped: customer phone is missing or invalid.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            NotificationLog::logWhatsapp([
                'type' => 'order_tickets',
                'recipient' => $order->customer->phone ?? null,
                'order_id' => $order->id,
                'subject' => $orderSubject,
                'status' => 'skipped',
                'error' => 'Phone missing or invalid',
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
                $msgId = $this->sendDocumentWithFallbacks(
                    phone: $phone,
                    ticketNumber: (string) $ticket->ticket_number,
                    caption: $caption,
                    fallbackText: $caption."\nDownload Link: ".$shortLink,
                    context: [
                        'order_id' => $order->id,
                        'ticket_number' => $ticket->ticket_number,
                    ],
                );
                NotificationLog::logWhatsapp([
                    'type' => 'order_tickets',
                    'recipient' => $phone,
                    'order_id' => $order->id,
                    'subject' => 'Ticket #'.$ticket->ticket_number.' — '.$orderSubject,
                    'status' => 'sent',
                    'message_id' => $msgId,
                ]);
            } catch (Throwable $exception) {
                Log::error('Order WhatsApp ticket send failed.', [
                    'order_id' => $order->id,
                    'ticket_number' => $ticket->ticket_number,
                    'error' => $exception->getMessage(),
                ]);
                NotificationLog::logWhatsapp([
                    'type' => 'order_tickets',
                    'recipient' => $phone,
                    'order_id' => $order->id,
                    'subject' => 'Ticket #'.$ticket->ticket_number.' — '.$orderSubject,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function sendTextMessage(string $phone, string $body): ?string
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

        return $this->assertSent((array) $response->json(), $response->body());
    }

    private function sendDocumentMessage(string $phone, string $documentUrl, string $filename, string $caption): ?string
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

        return $this->assertSent((array) $response->json(), $response->body());
    }

    private function sendDocumentWithFallbacks(string $phone, string $ticketNumber, string $caption, string $fallbackText, array $context = []): ?string
    {
        $filename = $this->ticketFilename($ticketNumber);
        $attempts = [
            ['type' => 'signed_url', 'document' => $this->signedPdfDownloadLink($ticketNumber)],
            ['type' => 'short_url', 'document' => $this->shortDownloadLink($ticketNumber)],
        ];

        try {
            $pdfBinary = $this->fetchPdfBinary($ticketNumber);
            $pdfBase64 = base64_encode($pdfBinary);

            $attempts[] = ['type' => 'base64_data_uri', 'document' => 'data:application/pdf;base64,'.$pdfBase64];
            $attempts[] = ['type' => 'base64_raw', 'document' => $pdfBase64];
        } catch (Throwable $exception) {
            Log::warning('UltraMsg PDF prefetch failed.', [
                ...$context,
                'ticket_number' => $ticketNumber,
                'error' => $exception->getMessage(),
            ]);
        }

        foreach ($attempts as $index => $attempt) {
            try {
                $msgId = $this->sendDocumentMessage(
                    phone: $phone,
                    documentUrl: (string) $attempt['document'],
                    filename: $filename,
                    caption: $caption,
                );

                return $msgId;
            } catch (Throwable $exception) {
                Log::warning('UltraMsg document attempt failed.', [
                    ...$context,
                    'attempt' => $index + 1,
                    'attempt_type' => $attempt['type'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->sendTextMessage($phone, $fallbackText);
    }

    private function fetchPdfBinary(string $ticketNumber): string
    {
        $response = Http::timeout(30)->get($this->signedPdfDownloadLink($ticketNumber));
        $response->throw();

        return $response->body();
    }

    private function assertSent(array $payload, string $rawBody): ?string
    {
        $sent = data_get($payload, 'sent');

        if (! in_array($sent, [true, 'true', 1, '1'], true)) {
            throw new RuntimeException('UltraMsg accepted request but did not mark it as sent. Response: '.$rawBody);
        }

        $id = (string) data_get($payload, 'id', '');

        return $id !== '' ? $id : null;
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

    private function isTicketWhatsappSendingEnabled(): bool
    {
        return (bool) SystemSettings::get('whatsapp_ticket_sending_enabled', true);
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
