<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappMessageService
{
    /**
     * @return array{sent: bool, skipped: bool, to: string, from: string, sid: ?string, status: ?string, reason: ?string}
     */
    public function sendText(string $to, string $message): array
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.whatsapp_from');
        $messagingServiceSid = (string) config('services.twilio.messaging_service_sid');

        $toNumber = $this->formatWhatsappNumber($to);
        $fromNumber = $this->formatWhatsappNumber($from);

        if ($accountSid === '' || $authToken === '' || ($from === '' && $messagingServiceSid === '')) {
            Log::warning('Twilio WhatsApp configuration is missing; skipped sending message.', ['to' => $toNumber]);

            return [
                'sent' => false,
                'skipped' => true,
                'to' => $toNumber,
                'from' => $fromNumber,
                'sid' => null,
                'status' => null,
                'reason' => 'missing_twilio_configuration',
            ];
        }

        $payload = [
            'To' => $toNumber,
            'Body' => $message,
        ];

        if ($messagingServiceSid !== '') {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $fromNumber;
        }

        $response = Http::asForm()
            ->timeout(20)
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

        $body = $response->json();

        if (! $response->successful()) {
            $twilioCode = (string) data_get($body, 'code', '');
            $twilioMessage = (string) data_get($body, 'message', 'Twilio request failed.');

            $reason = $twilioCode !== '' ? "twilio_{$twilioCode}" : 'twilio_request_failed';
            if ($twilioCode === '63007') {
                $reason = 'twilio_63007_invalid_whatsapp_from';
                $twilioMessage .= ' Check TWILIO_WHATSAPP_FROM or use TWILIO_MESSAGING_SERVICE_SID with a WhatsApp-enabled sender.';
            }

            return [
                'sent' => false,
                'skipped' => false,
                'to' => $toNumber,
                'from' => $fromNumber,
                'sid' => null,
                'status' => null,
                'reason' => trim($reason.': '.$twilioMessage),
            ];
        }

        return [
            'sent' => true,
            'skipped' => false,
            'to' => $toNumber,
            'from' => $fromNumber,
            'sid' => data_get($body, 'sid'),
            'status' => data_get($body, 'status'),
            'reason' => null,
        ];
    }

    private function formatWhatsappNumber(string $value): string
    {
        $number = trim($value);

        if (str_starts_with($number, 'whatsapp:')) {
            $number = substr($number, 9);
        }

        $normalized = preg_replace('/[^0-9+]/', '', $number) ?: '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (! str_starts_with($normalized, '+')) {
            if (str_starts_with($normalized, '0')) {
                $countryCode = ltrim((string) config('services.twilio.default_country_code', '+20'), '+');
                $normalized = '+'.$countryCode.substr($normalized, 1);
            } else {
                $normalized = '+'.$normalized;
            }
        }

        return 'whatsapp:'.$normalized;
    }
}
