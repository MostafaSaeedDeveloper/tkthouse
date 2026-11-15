<?php

namespace App\Services\Tickets;

use App\Mail\HolderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CustomerPortal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The buyer's tools for the people their tickets are for: change a holder's name or e-mail, re-send the ticket link.
 *
 * Rules: only before the ticket is used; never for canceled / refunded tickets; at most MAX_EMAIL_CHANGES moves per ticket; the QR code
 * is never regenerated (a move changes who may *open* the ticket, not the ticket); the old address loses access at once because access
 * follows the holder e-mail on the ticket; the new address is invited. Every change is recorded.
 */
class TicketHolderService
{
    public const MAX_EMAIL_CHANGES = 3;
    public const RESENDS_PER_HOUR = 3;

    /** Why this ticket's holder cannot be changed right now, or null when it can. */
    public function blockedReason(IssuedTicket $ticket): ?string
    {
        $ticket->loadMissing('order', 'dashboardTicket.event', 'orderItem.event');

        if ($ticket->order_id === null) {
            return 'Guest-list tickets are managed by the event team.';
        }

        $qr = CustomerPortal::qrState($ticket, $ticket->event());

        return match ($qr['state']) {
            'used' => 'This ticket was already used.',
            'canceled' => $qr['refunded'] ? 'This ticket was refunded.' : 'This ticket was canceled.',
            default => null,
        };
    }

    public function emailChangesLeft(IssuedTicket $ticket): int
    {
        return max(0, self::MAX_EMAIL_CHANGES - (int) $ticket->holder_email_changes);
    }

    /** @return array{name_changed: bool, email_changed: bool, invited: bool} */
    public function update(IssuedTicket $ticket, User $by, string $name, string $email): array
    {
        $name = trim($name);
        $email = mb_strtolower(trim($email));

        $result = DB::transaction(function () use ($ticket, $by, $name, $email) {
            $locked = IssuedTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($reason = $this->blockedReason($locked)) {
                throw ValidationException::withMessages(['holder' => $reason]);
            }

            $oldName = (string) $locked->holder_name;
            $oldEmail = mb_strtolower(trim((string) $locked->holder_email));
            $nameChanged = $name !== $oldName;
            $emailChanged = $email !== $oldEmail;

            if ($emailChanged && $this->emailChangesLeft($locked) < 1) {
                throw ValidationException::withMessages(['holder_email' => 'This ticket was already moved '.self::MAX_EMAIL_CHANGES.' times. Contact support to change it again.']);
            }
            if (! $nameChanged && ! $emailChanged) {
                return ['name_changed' => false, 'email_changed' => false, 'locked' => $locked];
            }

            $attrs = ['holder_name' => $name];
            if ($emailChanged) {
                $attrs += ['holder_email' => $email, 'holder_email_changes' => (int) $locked->holder_email_changes + 1, 'sent_at' => null];
            }
            $locked->forceFill($attrs)->save();

            // The ticket the gate scans and the purchase record stay in step. The QR payload is deliberately untouched.
            Ticket::query()->where('ticket_number', $locked->ticket_number)->update(['holder_name' => $name] + ($emailChanged ? ['holder_email' => $email] : []));
            if ($locked->orderItem && (int) $locked->orderItem->quantity === 1) {
                $locked->orderItem->forceFill(['holder_name' => $name] + ($emailChanged ? ['holder_email' => $email] : []))->save();
            }

            activity('tickets')->performedOn($locked)->causedBy($by)->withProperties([
                'order_id' => $locked->order_id,
                'name' => ['old' => $oldName, 'new' => $name],
                'email' => ['old' => $oldEmail, 'new' => $email],
            ])->log($emailChanged ? 'Ticket holder changed (e-mail moved)' : 'Ticket holder name changed');

            return ['name_changed' => $nameChanged, 'email_changed' => $emailChanged, 'locked' => $locked];
        });

        $invited = false;
        if ($result['email_changed']) {
            $invited = $this->sendInvite($result['locked']->fresh());
        }

        return ['name_changed' => $result['name_changed'], 'email_changed' => $result['email_changed'], 'invited' => $invited];
    }

    /** Sends the holder their ticket link again (3 per ticket per hour). */
    public function resend(IssuedTicket $ticket, User $by): string
    {
        if ($reason = $this->blockedReason($ticket)) {
            return $reason;
        }
        if (blank($ticket->holder_email)) {
            return 'This ticket has no e-mail address yet. Add one first.';
        }

        $key = 'ticket-resend:'.$ticket->id;
        if (RateLimiter::tooManyAttempts($key, self::RESENDS_PER_HOUR)) {
            return 'This ticket link was sent several times already. Please try again in a while.';
        }
        RateLimiter::hit($key, 3600);

        if (! $this->sendInvite($ticket)) {
            return 'We could not send the e-mail right now. Please try again.';
        }

        activity('tickets')->performedOn($ticket)->causedBy($by)->withProperties(['order_id' => $ticket->order_id])->log('Ticket link re-sent to the holder');

        return 'ok';
    }

    private function sendInvite(IssuedTicket $ticket): bool
    {
        $ticket->loadMissing('order.customer');

        try {
            Mail::to($ticket->holder_email)->send(new HolderTicketsIssuedMail($ticket->order, collect([$ticket]), mb_strtolower((string) $ticket->holder_email)));
            $ticket->forceFill(['sent_at' => now()])->saveQuietly();

            return true;
        } catch (\Throwable $e) {
            Log::error('Ticket invite failed.', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
