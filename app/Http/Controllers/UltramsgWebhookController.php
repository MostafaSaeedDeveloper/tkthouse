<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UltramsgWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Optional token check
        $token = config('services.ultramsg.token');
        $incoming = $request->input('token') ?? $request->query('token');
        if ($token && $incoming !== $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $eventType = $request->input('event_type');

        if ($eventType !== 'message_ack') {
            return response()->json(['ok' => true]);
        }

        $data = $request->input('data', []);
        $messageId = $data['id'] ?? null;
        $ack       = $data['ack'] ?? null;

        if (! $messageId || $ack === null) {
            return response()->json(['ok' => true]);
        }

        $deliveryStatus = $this->resolveAck($ack);

        $log = NotificationLog::where('message_id', $messageId)->first();

        if (! $log) {
            Log::debug('UltraMsg webhook: no matching notification_log.', [
                'message_id' => $messageId,
                'ack'        => $ack,
            ]);

            return response()->json(['ok' => true]);
        }

        $log->update([
            'delivery_status' => $deliveryStatus,
            'metadata'        => array_merge($log->metadata ?? [], [
                'wa_ack'       => $ack,
                'wa_timestamp' => $data['timestamp'] ?? null,
            ]),
        ]);

        return response()->json(['ok' => true]);
    }

    private function resolveAck(mixed $ack): string
    {
        // UltraMsg sends ack as string ("sent", "delivered", "read")
        // or occasionally as integer (1, 2, 3)
        return match ((string) $ack) {
            '1', 'sent', 'device' => 'wa_sent',
            '2', 'delivered'      => 'wa_delivered',
            '3', 'read', 'played' => 'wa_read',
            default               => 'wa_'.$ack,
        };
    }
}
