<?php

namespace App\Services\Scanner;

use App\Models\Event;
use App\Models\Order;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place a ticket is admitted at the gate.
 *
 * Everything is decided on the server, inside a transaction, against the row locked with SELECT ... FOR UPDATE
 * (the same row lock RefundService takes when it cancels a ticket, so a refund and a scan are serialised too).
 * The final write is also a conditional UPDATE (… WHERE status = 'not_checked_in'), so even if the lock were ever
 * bypassed, at most one writer can flip the ticket. The caller learns "approved" only after that row is changed
 * and its scan record inserted in the same committed transaction.
 *
 * Nothing here ever turns a refused ticket into an admitted one on retry: a retry just re-runs the same checks.
 */
class CheckInService
{
    public const APPROVED = 'approved';       // green  — the database now says checked_in, by THIS request
    public const DUPLICATE = 'duplicate';     // red    — it was already checked in earlier
    public const REJECTED = 'rejected';       // red    — refunded / canceled / wrong event
    public const REVIEW = 'review';           // amber  — order not in an admittable state; do not admit, ask a supervisor
    public const NOT_FOUND = 'not_found';     // red    — no such ticket

    /** Order states whose tickets may enter. partially_refunded: only the tickets the refund cancelled are cancelled, the rest stay valid. */
    private const ADMITTABLE_ORDER_STATUSES = ['paid', 'partially_refunded'];

    /**
     * @param  int|null  $selectedEventId  the event this device is working (must be one the user is allowed to scan)
     * @return array{result:string, code:string, message:string, ticket:?array}
     */
    public function checkIn(User $user, string $payload, ?int $selectedEventId, ?Request $request = null): array
    {
        $scope = $this->scope($user, $selectedEventId);
        if ($scope['error']) {
            return $this->out(self::REJECTED, 'event_not_allowed', 'This device is not allowed to scan that event.', null);
        }

        $ticketId = $this->resolveTicketId($payload);
        if (! $ticketId) {
            $this->log($user, $request, null, 'lookup_failed', $payload, null, null);

            return $this->out(self::NOT_FOUND, 'not_found', 'Ticket not found. Scan again or type the ticket number.', null);
        }

        $reject = null;   // [result, code, message, logAction]
        $dup = null;
        $approvedTicket = null;

        // 3 attempts so a rare InnoDB deadlock victim is retried instead of surfacing as an error.
        DB::transaction(function () use ($user, $request, $payload, $ticketId, $scope, &$reject, &$dup, &$approvedTicket) {
            $reject = $dup = $approvedTicket = null;

            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->whereKey($ticketId)->lockForUpdate()->first();
            if (! $ticket) {
                $reject = [self::NOT_FOUND, 'not_found', 'Ticket not found.', 'lookup_failed'];

                return;
            }

            // 1. Refunded / canceled — never admitted, never re-opened here.
            if ($ticket->isRefundCanceled()) {
                $reject = [self::REJECTED, 'refunded', 'REFUNDED / CANCELED — '.$ticket->refundCancellationText().'. Entry is not allowed.', 'checkin_rejected_refunded'];

                return;
            }
            if ($ticket->status === 'canceled') {
                $reject = [self::REJECTED, 'canceled', 'CANCELED ticket. Do not admit.', 'checkin_rejected_canceled'];

                return;
            }

            // 2. Event authorisation (by event id; the title is only a legacy fallback for rows that have no id at all).
            $ticketEventId = $ticket->event_id ?: ($ticket->order_item_id ? DB::table('order_items')->where('id', $ticket->order_item_id)->value('event_id') : null);
            if (! $this->eventAllowed($scope, $ticketEventId ? (int) $ticketEventId : null, (string) $ticket->name)) {
                $reject = [self::REJECTED, 'wrong_event', 'WRONG EVENT — this ticket is for "'.$ticket->eventLabel().'".', 'checkin_rejected_wrong_event'];

                return;
            }

            // 3. Order / payment state (guest-list tickets have no order).
            if ($ticket->order_id) {
                $orderStatus = Order::query()->whereKey($ticket->order_id)->value('status'); // soft-deleted orders → null
                if (! in_array($orderStatus, self::ADMITTABLE_ORDER_STATUSES, true)) {
                    $reject = [self::REVIEW, 'order_not_paid', 'ORDER NOT PAID'.($orderStatus ? ' ('.str_replace('_', ' ', $orderStatus).')' : '').'. Do not admit — ask a supervisor.', 'checkin_rejected_order'];

                    return;
                }
            }

            // 4. Already in?
            if ($ticket->status === 'checked_in') {
                $dup = $ticket;

                return;
            }
            if ($ticket->status !== 'not_checked_in') {
                $reject = [self::REVIEW, 'unknown_status', 'Ticket is in an unexpected state. Do not admit — ask a supervisor.', 'checkin_rejected_status'];

                return;
            }

            // 5. Admit — conditional update, exactly once.
            $now = now();
            $changed = Ticket::query()->whereKey($ticket->id)->where('status', 'not_checked_in')
                ->update(['status' => 'checked_in', 'checked_in_at' => $now, 'canceled_at' => null, 'updated_at' => $now]);
            if ($changed !== 1) {
                $dup = $ticket->fresh();

                return;
            }

            $ticket->forceFill(['status' => 'checked_in', 'checked_in_at' => $now]);
            // 'status_update' + new_status=checked_in is what the dashboards and scanner statistics already count as a check-in.
            $this->log($user, $request, $ticket, 'status_update', $payload, 'not_checked_in', 'checked_in');
            $approvedTicket = $ticket;
        }, 3);

        if ($approvedTicket) {
            return $this->out(self::APPROVED, 'approved', 'ENTRY APPROVED', $this->describe($approvedTicket, $user));
        }

        if ($dup) {
            $this->log($user, $request, $dup, 'checkin_duplicate', $payload, 'checked_in', 'checked_in');

            return $this->out(self::DUPLICATE, 'already_checked_in', 'ALREADY CHECKED IN', $this->describe($dup, $user, true));
        }

        [$result, $code, $message, $action] = $reject;
        $failed = Ticket::query()->find($ticketId);
        $this->log($user, $request, $result === self::NOT_FOUND ? null : $failed, $action, $payload, $failed?->status, $failed?->status);

        // A rejected ticket reveals only what staff need to act: its number and event, never holder contact or payment data.
        return $this->out($result, $code, $message, $failed && $result !== self::NOT_FOUND ? ['number' => $failed->ticket_number, 'event' => $failed->eventLabel(), 'type' => $failed->ticketTypeLabel(), 'holder' => null] : null);
    }

    /**
     * Read-only: what is the current state of this ticket? Used after an unknown outcome (timeout) and by manual search.
     * Never changes anything.
     */
    public function lookup(User $user, string $payload, ?Request $request, ?int $selectedEventId): array
    {
        $scope = $this->scope($user, $selectedEventId);
        if ($scope['error']) {
            return $this->out(self::REJECTED, 'event_not_allowed', 'This device is not allowed to scan that event.', null);
        }
        $ticketId = $this->resolveTicketId($payload);
        $ticket = $ticketId ? Ticket::query()->find($ticketId) : null;
        if (! $ticket) {
            return $this->out(self::NOT_FOUND, 'not_found', 'Ticket not found.', null);
        }
        $ticketEventId = $ticket->event_id ?: ($ticket->order_item_id ? DB::table('order_items')->where('id', $ticket->order_item_id)->value('event_id') : null);
        if (! $this->eventAllowed($scope, $ticketEventId ? (int) $ticketEventId : null, (string) $ticket->name)) {
            return $this->out(self::REJECTED, 'wrong_event', 'WRONG EVENT — this ticket is for "'.$ticket->eventLabel().'".', ['number' => $ticket->ticket_number, 'event' => $ticket->eventLabel(), 'type' => $ticket->ticketTypeLabel(), 'holder' => null]);
        }

        if ($ticket->isRefundCanceled()) {
            $this->log($user, $request, $ticket, 'lookup_rejected_refunded', $payload, $ticket->status, $ticket->status);
        }

        $state = match (true) {
            $ticket->isRefundCanceled() => [self::REJECTED, 'refunded', 'REFUNDED / CANCELED — '.$ticket->refundCancellationText().'. Entry is not allowed.'],
            $ticket->status === 'canceled' => [self::REJECTED, 'canceled', 'CANCELED ticket. Do not admit.'],
            $ticket->status === 'checked_in' => [self::DUPLICATE, 'already_checked_in', 'ALREADY CHECKED IN'],
            default => ['ready', 'ready', 'Ticket is valid and not yet checked in.'],
        };

        if (in_array($state[1], ['ready', 'already_checked_in'], true)) {
            $this->log($user, $request, $ticket, 'lookup_success', $payload, $ticket->status, $ticket->status); // existing audit record type, kept
        }

        return $this->out($state[0], $state[1], $state[2], $this->describe($ticket, $user, $ticket->status === 'checked_in'));
    }

    // ───────────────────────────── internals ─────────────────────────────

    /** @return array{error:bool, restricted:bool, ids:array<int>, names:array<string>} */
    private function scope(User $user, ?int $selectedEventId): array
    {
        $assigned = $user->managedEvents()->get(['events.id', 'events.name']);
        $restricted = $assigned->isNotEmpty();
        $ids = $assigned->pluck('id')->map(fn ($i) => (int) $i)->all();
        $names = $assigned->pluck('name')->all();

        if ($selectedEventId) {
            if ($restricted && ! in_array($selectedEventId, $ids, true)) {
                return ['error' => true, 'restricted' => true, 'ids' => [], 'names' => []];
            }
            $name = Event::query()->whereKey($selectedEventId)->value('name');
            if ($name === null) {
                return ['error' => true, 'restricted' => true, 'ids' => [], 'names' => []];
            }
            $restricted = true;
            $ids = [$selectedEventId];
            $names = [$name];
        }

        return ['error' => false, 'restricted' => $restricted, 'ids' => $ids, 'names' => $names];
    }

    private function eventAllowed(array $scope, ?int $ticketEventId, string $ticketName): bool
    {
        if (! $scope['restricted']) {
            return true; // legacy: a scanner with no assignment and no selected event may scan any event
        }
        if ($ticketEventId !== null) {
            return in_array($ticketEventId, $scope['ids'], true);
        }
        // Old rows without any event id: fall back to the "Event - Ticket type" text, exactly like before.
        foreach ($scope['names'] as $name) {
            if ($ticketName === $name || str_starts_with($ticketName, $name.' - ')) {
                return true;
            }
        }

        return false;
    }

    /** Indexed lookups first (ticket_number is unique); the un-indexed qr_payload column is only consulted on a miss. */
    private function resolveTicketId(string $payload): ?int
    {
        $payload = trim($payload);
        if ($payload === '' || mb_strlen($payload) > 1000) {
            return null;
        }
        $candidates = [$payload];
        if (preg_match('/\d{8,}/', $payload, $m) === 1 && $m[0] !== $payload) {
            array_unshift($candidates, $m[0]); // legacy: the ticket number may be embedded in a URL-style payload
        }
        foreach ($candidates as $number) {
            $id = Ticket::query()->where('ticket_number', $number)->value('id');
            if ($id) {
                return (int) $id;
            }
        }
        $id = Ticket::query()->where('qr_payload', $payload)->value('id');

        return $id ? (int) $id : null;
    }

    /** Minimal, gate-safe description of a ticket: no e-mail, phone, order number or amounts. */
    private function describe(Ticket $ticket, User $user, bool $withOriginal = false): array
    {
        $d = [
            'number' => $ticket->ticket_number,
            'holder' => $ticket->holder_name ?: null,
            'type' => $ticket->ticketTypeLabel(),
            'event' => $ticket->eventLabel(),
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
        ];

        if ($withOriginal) {
            $orig = ScanLog::query()->where('ticket_id', $ticket->id)->where('action', 'status_update')->where('new_status', 'checked_in')
                ->orderBy('scanned_at')->orderBy('id')->first(['scanned_by_user_id', 'scanner_name', 'scanned_at']);
            $at = $orig?->scanned_at ?? $ticket->checked_in_at;
            $d['checked_in_at'] = $at?->toIso8601String();
            $d['checked_in_by'] = $orig?->scanner_name;
            $d['checked_in_by_you'] = $orig && (int) $orig->scanned_by_user_id === (int) $user->id;
            $d['seconds_ago'] = $at ? max(0, (int) $at->diffInSeconds(now(), true)) : null;
        }

        return $d;
    }

    private function log(User $user, ?Request $request, ?Ticket $ticket, string $action, string $payload, ?string $from, ?string $to): void
    {
        ScanLog::create([
            'ticket_id' => $ticket?->id,
            'event_id' => $ticket?->event_id,
            'event_name' => $ticket?->eventLabel(),
            'ticket_number' => $ticket?->ticket_number ?? (preg_match('/\d{8,}/', $payload, $m) === 1 ? $m[0] : mb_substr($payload, 0, 100)),
            'action' => $action,
            'previous_status' => $from,
            'new_status' => $to,
            'payload' => mb_substr($payload, 0, 1000),
            'scanned_by_user_id' => $user->id,
            'scanner_name' => $user->name,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'scanned_at' => now(),
        ]);
    }

    private function out(string $result, string $code, string $message, ?array $ticket): array
    {
        return ['result' => $result, 'code' => $code, 'message' => $message, 'ticket' => $ticket];
    }
}
