<?php

namespace App\Services;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappNotificationService
{
    public function sendOrderTickets(Order $order): bool
    {
        $order->loadMissing(['issuedTickets', 'customer']);

        $phone = $this->formatTwilioWhatsappAddress($order->customer?->phone);
        if (! $phone) {
            Log::info('Skipping WhatsApp order notification: customer phone is missing or invalid.', ['order_id' => $order->id, 'phone' => $order->customer?->phone]);

            return false;
        }

        $body = $this->buildOrderMessage($order);

        return $this->dispatchMessage($phone, $body, [
            'context' => 'order_paid',
            'order_id' => $order->id,
        ])['ok'];
    }

    public function sendSingleTicket(Ticket $ticket): bool
    {
        return $this->sendSingleTicketWithDetails($ticket)['ok'];
    }

    public function sendSingleTicketWithDetails(Ticket $ticket): array
    {
        $ticket->loadMissing('order.customer');

        $rawPhone = $ticket->holder_phone ?: $ticket->order?->customer?->phone;
        $phone = $this->formatTwilioWhatsappAddress($rawPhone);
        if (! $phone) {
            Log::info('Skipping WhatsApp ticket notification: holder/customer phone is missing or invalid.', [
                'ticket_id' => $ticket->id,
                'holder_phone' => $ticket->holder_phone,
                'customer_phone' => $ticket->order?->customer?->phone,
            ]);

            return [
                'ok' => false,
                'message' => 'Phone number is missing or invalid. Use international format like +2010xxxxxxx.',
            ];
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

    private function dispatchMessage(string $to, string $body, array $context = []): array
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.auth_token');
        $from = $this->formatTwilioWhatsappAddress((string) config('services.twilio.whatsapp_from'));

        if (! $sid || ! $token || ! $from) {
            Log::info('Skipping WhatsApp notification: Twilio is not fully configured.', $context);

            return ['ok' => false, 'message' => 'Twilio credentials are missing or invalid.'];
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid);

        try {
            $response = $this->postTwilioWithRetry($url, $sid, $token, [
                'From' => $from,
                'To' => $to,
                'Body' => $body,
            ], $context + ['to' => $to]);

            if ($response->failed()) {
                $status = $response->status();
                Log::warning('Twilio WhatsApp request failed.', [
                    ...$context,
                    'to' => $to,
                    'status' => $status,
                    'response' => $response->json() ?: $response->body(),
                ]);

                if ($status === 429) {
                    return ['ok' => false, 'message' => 'Twilio rate limit reached (429). Please retry after a few seconds.'];
                }

                return ['ok' => false, 'message' => 'Twilio HTTP error: '.$status.'.'];
            }

            $payload = $response->json() ?: [];
            $messageSid = $payload['sid'] ?? null;
            $messageStatus = $payload['status'] ?? null;
            $errorCode = $payload['error_code'] ?? null;

            if ($errorCode) {
                Log::warning('Twilio WhatsApp accepted request with error_code.', [
                    ...$context,
                    'to' => $to,
                    'sid' => $messageSid,
                    'status' => $messageStatus,
                    'error_code' => $errorCode,
                    'error_message' => $payload['error_message'] ?? null,
                ]);

                return ['ok' => false, 'message' => (string) ($payload['error_message'] ?? 'Twilio rejected the request. Error code: '.$errorCode)];
            }

            if (! filled($messageSid)) {
                Log::warning('Twilio WhatsApp response missing message SID.', [
                    ...$context,
                    'to' => $to,
                    'status' => $messageStatus,
                    'response' => $payload,
                ]);

                return ['ok' => false, 'message' => 'Twilio response did not include a message SID.'];
            }

            Log::info('Twilio WhatsApp message queued/sent.', [
                ...$context,
                'to' => $to,
                'sid' => $messageSid,
                'status' => $messageStatus,
            ]);

            if (filled($messageSid) && in_array($messageStatus, ['accepted', 'queued', 'sending', 'sent', 'delivered', 'read'], true)) {
                return ['ok' => true, 'message' => 'Message queued successfully.', 'sid' => $messageSid, 'status' => $messageStatus];
            }

            return ['ok' => false, 'message' => 'Twilio status is not deliverable yet: '.($messageStatus ?: 'unknown').'.', 'sid' => $messageSid, 'status' => $messageStatus];
        } catch (Throwable $exception) {
            Log::error('Twilio WhatsApp request threw an exception.', [
                ...$context,
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Twilio request failed: '.$exception->getMessage()];
        }
    }


    private function postTwilioWithRetry(string $url, string $sid, string $token, array $payload, array $context = []): \Illuminate\Http\Client\Response
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::asForm()
                ->timeout(15)
                ->withBasicAuth($sid, $token)
                ->post($url, $payload);

            if ($response->status() !== 429 || $attempt === $maxAttempts) {
                return $response;
            }

            $retryAfter = (int) ($response->header('Retry-After') ?? 0);
            $sleepSeconds = $retryAfter > 0 ? min($retryAfter, 5) : $attempt;

            Log::warning('Twilio rate limit hit; retrying WhatsApp request.', [
                ...$context,
                'attempt' => $attempt,
                'retry_after' => $retryAfter,
                'sleep_seconds' => $sleepSeconds,
            ]);

            sleep($sleepSeconds);
        }

        return Http::asForm()->timeout(15)->withBasicAuth($sid, $token)->post($url, $payload);
    }

    private function formatTwilioWhatsappAddress(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'whatsapp:')) {
            $value = substr($value, strlen('whatsapp:'));
        }

        $value = preg_replace('/[^\d+]/', '', $value) ?? '';

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (! str_starts_with($value, '+')) {
            $digits = preg_replace('/\D/', '', $value) ?? '';
            if ($digits === '') {
                return null;
            }

            if (str_starts_with($digits, '0')) {
                $defaultCountryCode = ltrim((string) config('services.twilio.default_country_code', '20'), '+');
                $digits = $defaultCountryCode.ltrim($digits, '0');
            }

            $value = '+'.$digits;
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            return null;
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
