<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{
    public function verify(): JsonResponse
    {
        $data = request()->validate([
            'qr_token' => ['required', 'string'],
            'event_id' => ['required', 'integer'],
        ]);

        $ticket = Ticket::query()->where('qr_token', $data['qr_token'])->where('event_id', $data['event_id'])->first();

        if (! $ticket) {
            return response()->json(['result' => 'invalid'], 404);
        }

        if ($ticket->used_at) {
            return response()->json(['result' => 'duplicate', 'ticket' => $ticket]);
        }

        return response()->json(['result' => 'valid', 'ticket' => $ticket]);
    }

    public function confirm(): JsonResponse
    {
        $data = request()->validate([
            'qr_token' => ['required', 'string'],
            'event_id' => ['required', 'integer'],
            'gate' => ['nullable', 'string', 'max:100'],
        ]);

        $ticket = Ticket::query()->where('qr_token', $data['qr_token'])->where('event_id', $data['event_id'])->first();

        if (! $ticket) {
            return response()->json(['result' => 'invalid'], 404);
        }

        $result = DB::transaction(function () use ($ticket, $data): string {
            $duplicate = $ticket->fresh()->used_at !== null;

            if (! $duplicate) {
                $ticket->update(['used_at' => now(), 'status' => 'used']);
            }

            Checkin::create([
                'ticket_id' => $ticket->id,
                'event_id' => $ticket->event_id,
                'checked_in_by_user_id' => auth()->id() ?? 1,
                'gate' => $data['gate'] ?? null,
                'device_id' => request()->header('X-Device-Id'),
                'result' => $duplicate ? 'duplicate' : 'success',
            ]);

            return $duplicate ? 'duplicate' : 'success';
        });

        return response()->json(['result' => $result, 'ticket' => $ticket->fresh()]);
    }
}
