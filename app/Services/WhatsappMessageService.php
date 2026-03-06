<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappMessageService
{
    public function sendText(string $to, string $message): bool
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.whatsapp_from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            Log::info('Twilio WhatsApp configuration is missing; skipped sending message.', ['to' => $to]);

            return false;
        }

        $toNumber = $this->formatWhatsappNumber($to);
        $fromNumber = $this->formatWhatsappNumber($from);

        Http::asForm()
            ->timeout(20)
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $fromNumber,
                'To' => $toNumber,
                'Body' => $message,
            ])
            ->throw();

        return true;
    }

    private function formatWhatsappNumber(string $value): string
    {
        $number = trim($value);

        if (str_starts_with($number, 'whatsapp:')) {
            return $number;
        }

        return 'whatsapp:'.$number;
    }
}
