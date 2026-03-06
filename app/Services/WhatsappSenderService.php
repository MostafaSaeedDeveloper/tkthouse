<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappSenderService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.enabled', true)
            && filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.base_url'));
    }

    public function sendMessage(string $phone, string $message): Response
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('WhatsApp sender is not configured.');
        }

        $url = rtrim((string) config('services.whatsapp.base_url'), '/').'/'.ltrim((string) config('services.whatsapp.endpoint', 'api/send-message'), '/');

        $payload = [
            (string) config('services.whatsapp.recipient_field', 'to') => $this->normalizePhone($phone),
            (string) config('services.whatsapp.message_field', 'text') => $message,
        ];

        if (filled(config('services.whatsapp.instance_id'))) {
            $payload[(string) config('services.whatsapp.instance_field', 'instance_id')] = (string) config('services.whatsapp.instance_id');
        }

        return Http::timeout(20)
            ->acceptJson()
            ->withToken((string) config('services.whatsapp.token'))
            ->post($url, $payload)
            ->throw();
    }

    private function normalizePhone(string $phone): string
    {
        $value = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if ($value !== '' && str_starts_with($value, '00')) {
            return '+'.substr($value, 2);
        }

        return $value;
    }
}
