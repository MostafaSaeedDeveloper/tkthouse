<?php

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Support\SystemSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Makes the .pkpass through the WalletWallet service (api.walletwallet.dev): they sign it with their own Apple certificate. */
class WalletWalletClient
{
    private const ENDPOINT = 'https://api.walletwallet.dev/api/pkpass';

    public static function configured(): bool
    {
        return filled(WalletSettings::secrets()['ww_api_key']);
    }

    /** @return string path of a temporary .pkpass file (the caller deletes it after sending) */
    public function build(IssuedTicket $ticket, ?Event $event): string
    {
        $key = WalletSettings::secrets()['ww_api_key'];
        if (! $key) {
            throw new RuntimeException('WalletWallet is not configured.');
        }

        $response = Http::withToken($key)->acceptJson()->timeout(20)->post(self::ENDPOINT, $this->payload($ticket, $event));

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'pkpass')) {
            throw new RuntimeException('WalletWallet refused the request ('.$response->status().'): '.mb_substr((string) ($response->json('error') ?? $response->body()), 0, 200));
        }

        $path = tempnam(sys_get_temp_dir(), 'pkpass');
        file_put_contents($path, $response->body());

        return $path;
    }

    public function payload(IssuedTicket $ticket, ?Event $event): array
    {
        $eventName = $event?->name ?: ($ticket->ticket_name ?: 'Event ticket');
        $org = WalletSettings::organizationName();

        $payload = [
            'barcodeValue' => (string) $ticket->ticket_number, // exactly what the web QR encodes, so the scanner reads it the same way
            'barcodeFormat' => 'QR',
            'logoText' => $org,
            'description' => $eventName,
            'headerFields' => [['label' => 'TICKET', 'value' => '#'.$ticket->ticket_number]],
            'primaryFields' => [['label' => 'EVENT', 'value' => $eventName]],
            'secondaryFields' => array_values(array_filter([
                $event?->event_date ? ['label' => 'DATE', 'value' => (string) $event->formatted_date_range] : null,
                $event?->event_time ? ['label' => 'TIME', 'value' => Carbon::parse($event->event_time)->format('g:i A')] : null,
            ])),
            'backFields' => array_values(array_filter([
                ['label' => 'Attendee', 'value' => $ticket->holder_name ?: 'Attendee'],
                ['label' => 'Ticket type', 'value' => (string) $ticket->ticket_name],
                $event?->location ? ['label' => 'Venue', 'value' => (string) $event->location] : null,
                $event?->map_url ? ['label' => 'Map', 'value' => (string) $event->map_url] : null,
            ])),
            'colorPreset' => 'dark',
        ];

        if ($event?->event_date) {
            $end = ($event->end_date ?: $event->event_date)->copy()->endOfDay()->addDay();
            $payload['expirationDays'] = max(1, min(3650, (int) ceil(now()->diffInDays($end, false)) ?: 1));
        }

        if (SystemSettings::get('wallet_ww_pro')) { // custom colour and logo are Pro-only; the Free plan would reject them
            $payload['color'] = (string) SystemSettings::get('secondary_color', '#111111');
            unset($payload['colorPreset']);
            $logo = SystemSettings::get('site_logo_light');
            $payload['logoURL'] = $logo ? asset('storage/'.$logo) : asset('images/logo-light.png');
        }

        return $payload;
    }
}
