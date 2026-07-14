<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrevoWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Optional secret token check via URL query param ?secret=xxx
        $secret = config('services.brevo.webhook_secret');
        if ($secret && $request->query('secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // Brevo can POST a single event or a batch array
        $events = isset($payload[0]) ? $payload : [$payload];

        foreach ($events as $event) {
            $this->processEvent((array) $event);
        }

        return response()->json(['ok' => true]);
    }

    private function processEvent(array $event): void
    {
        $rawMessageId = $event['message-id'] ?? null;
        $brevoEvent   = $event['event'] ?? null;

        if (! $rawMessageId || ! $brevoEvent) {
            return;
        }

        $messageId = trim((string) $rawMessageId, '<>');

        $log = NotificationLog::where('message_id', $messageId)->first();

        if (! $log) {
            Log::debug('Brevo webhook: no matching notification_log.', [
                'message_id' => $messageId,
                'event'      => $brevoEvent,
            ]);

            return;
        }

        $deliveryStatus = match ($brevoEvent) {
            'request'       => 'queued',
            'delivered'     => 'delivered',
            'soft_bounce'   => 'soft_bounce',
            'hard_bounce'   => 'hard_bounce',
            'spam'          => 'spam',
            'opened'        => 'opened',
            'click'         => 'clicked',
            'unsubscribe'   => 'unsubscribed',
            'invalid_email' => 'invalid_email',
            'blocked'       => 'blocked',
            default         => $brevoEvent,
        };

        $updateData = [
            'delivery_status' => $deliveryStatus,
            'metadata'        => array_merge($log->metadata ?? [], [
                'brevo_event' => $brevoEvent,
                'brevo_date'  => $event['date'] ?? null,
                'brevo_ip'    => $event['sending_ip'] ?? null,
            ]),
        ];

        // Treat hard failures as failed
        if (in_array($brevoEvent, ['hard_bounce', 'invalid_email', 'blocked'])) {
            $updateData['status'] = 'failed';
            $updateData['error']  = 'Brevo: '.$brevoEvent.(isset($event['reason']) ? ' — '.$event['reason'] : '');
        }

        $log->update($updateData);
    }
}
