<?php

namespace App\Services\Tickets;

use App\Jobs\SendQrReleaseBatch;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Your QR code is available" e-mails.
 *
 * A ticket is notified only if its QR was LOCKED when it was issued and it later became unlocked, once. Per ticket the state is
 * not_eligible → pending → sent | failed. `sent` is final: a scheduler tick, Show/Hide/Show or a re-activation never notifies it again;
 * only `failed` tickets can be retried (by an admin). Nothing here sends mail itself: it marks tickets and queues SendQrReleaseBatch.
 *
 * Per event the mode is TEST (default; nothing reaches customers, the sandbox is used) or LIVE (explicitly activated by an admin after
 * a recipient preview, choosing to notify the tickets that are already eligible or to skip them).
 */
class QrReleaseService
{
    public const CHUNK = 1000;
    public const STALE_CLAIM_MINUTES = 10;

    /** The single rule, shared with the test sandbox: only a locked-at-issue ticket that is still waiting becomes pending, and only while the QR is visible. */
    public static function shouldBecomePending(string $status, bool $lockedAtIssue, bool $visible, bool $skipped = false): bool
    {
        return $visible && $lockedAtIssue && ! $skipped && $status === IssuedTicket::NOTICE_NOT_ELIGIBLE;
    }

    /** Valid tickets only: not canceled / refunded / used / deleted, order paid or partially refunded (guest-list tickets have no order). */
    private function valid(Builder $q): Builder
    {
        return $q->whereIn('ticket_number', Ticket::query()->select('ticket_number')->whereNotIn('status', ['canceled', 'checked_in']))
            ->where(fn ($w) => $w->whereNull('order_id')->orWhereIn('order_id', Order::query()->select('id')->whereIn('status', ['paid', 'partially_refunded'])));
    }

    private function waiting(Event $event): Builder
    {
        return IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_NOT_ELIGIBLE)
            ->where('qr_locked_at_issue', true)->whereNull('qr_notice_skipped_at');
    }

    /** Called by the scheduler and right after any change of QR visibility. Cheap: marks tickets and queues work, sends nothing. */
    public function process(Event $event): int
    {
        $event->refresh();
        if (! $event->qrReleaseIsLive() || ! $event->qrIsVisible()) {
            return 0;
        }

        $this->recoverStaleClaims($event);
        $marked = $this->markWaitingAsPending($event);

        if (IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)->whereNull('qr_notice_claim')->exists()) {
            SendQrReleaseBatch::dispatch($event->id);
        }

        return $marked;
    }

    public function onVisibilityChanged(Event $event): void
    {
        $this->process($event);   // Hide does nothing (not visible → returns); Show / automatic reveal marks and queues
    }

    private function markWaitingAsPending(Event $event): int
    {
        $n = 0;
        do {
            $ids = $this->valid($this->waiting($event))->limit(self::CHUNK)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            IssuedTicket::query()->whereIn('id', $ids)->update(['qr_notice_status' => IssuedTicket::NOTICE_PENDING, 'qr_notice_eligible_at' => now(), 'qr_notice_error' => null]);
            $n += $ids->count();
        } while (true);

        return $n;
    }

    /** A worker that died mid-send leaves claimed rows. Whether its e-mail left is unknown, so they are NOT re-sent automatically: they become failed for an admin to retry. */
    public function recoverStaleClaims(Event $event): int
    {
        return IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)
            ->whereNotNull('qr_notice_claim')->where('qr_notice_claimed_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES))
            ->update(['qr_notice_status' => IssuedTicket::NOTICE_FAILED, 'qr_notice_claim' => null, 'qr_notice_error' => 'Interrupted while sending: delivery unknown. Check before retrying.']);
    }

    /** What activating Live would act on right now. */
    public function preview(Event $event): array
    {
        $visible = $event->qrIsVisible();
        $eligible = $this->valid(IssuedTicket::query()->where('event_id', $event->id)->where(function ($q) {
            $q->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)
                ->orWhere(fn ($w) => $w->where('qr_notice_status', IssuedTicket::NOTICE_NOT_ELIGIBLE)->where('qr_locked_at_issue', true)->whereNull('qr_notice_skipped_at'));
        }));

        $tickets = (clone $eligible)->count();
        $recipients = (clone $eligible)->whereNotNull('holder_email')->where('holder_email', '!=', '')->distinct()->count(DB::raw('LOWER(holder_email)'));

        return [
            'visible' => $visible,
            // Visible now: these would be e-mailed right after activation (unless skipped). Hidden: they wait for the reveal.
            'tickets_now' => $visible ? $tickets : 0,
            'recipients_now' => $visible ? $recipients : 0,
            'tickets_waiting' => $visible ? 0 : $tickets,
            'recipients_waiting' => $visible ? 0 : $recipients,
        ];
    }

    /** @param 'notify'|'skip' $choice what to do with tickets that are ALREADY eligible at the moment of activation */
    public function activateLive(Event $event, ?User $by, string $choice): void
    {
        if (! in_array($choice, ['notify', 'skip'], true)) {
            throw ValidationException::withMessages(['choice' => 'Choose whether to notify the already-eligible tickets or skip them.']);
        }

        $before = $this->preview($event);
        $skipped = 0;

        DB::transaction(function () use ($event, $by, $choice, &$skipped) {
            $event->forceFill(['qr_release_mode' => Event::RELEASE_LIVE, 'qr_release_activated_at' => now(), 'qr_release_activated_by' => $by?->id])->save();

            if ($choice === 'skip' && $event->qrIsVisible()) {
                // Only what would be sent right now is skipped; tickets that unlock later are still notified.
                $skipped += $this->waiting($event)->update(['qr_notice_skipped_at' => now()]);
                $skipped += IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)->whereNull('qr_notice_claim')
                    ->update(['qr_notice_status' => IssuedTicket::NOTICE_NOT_ELIGIBLE, 'qr_notice_skipped_at' => now()]);
            }
        });

        activity('events')->performedOn($event)->causedBy($by)->withProperties(['choice' => $choice, 'preview' => $before, 'skipped' => $skipped])->log('QR release e-mails set to LIVE ('.$choice.')');

        if ($choice === 'notify') {
            $this->process($event);
        }
    }

    public function switchToTest(Event $event, ?User $by): void
    {
        $event->forceFill(['qr_release_mode' => Event::RELEASE_TEST])->save();
        activity('events')->performedOn($event)->causedBy($by)->log('QR release e-mails set to TEST (sending paused)');
    }

    /** Failed tickets only, back to pending; sent tickets are never touched. */
    public function retryFailed(Event $event, ?User $by): int
    {
        if (! $event->qrReleaseIsLive()) {
            throw ValidationException::withMessages(['retry' => 'Retry works in Live mode only.']);
        }

        $n = IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_FAILED)
            ->update(['qr_notice_status' => IssuedTicket::NOTICE_PENDING, 'qr_notice_claim' => null, 'qr_notice_claimed_at' => null, 'qr_notice_error' => null]);

        activity('events')->performedOn($event)->causedBy($by)->withProperties(['tickets' => $n])->log('QR release e-mails: failed notifications retried');
        $this->process($event);

        return $n;
    }

    /** Counts for the admin summary. */
    public function summary(Event $event): array
    {
        $rows = IssuedTicket::query()->where('event_id', $event->id)
            ->selectRaw('qr_notice_status as st, qr_locked_at_issue as locked, (qr_notice_skipped_at is not null) as skipped, count(*) as n')
            ->groupBy('qr_notice_status', 'qr_locked_at_issue', DB::raw('(qr_notice_skipped_at is not null)'))->get();

        $sum = fn (callable $f) => (int) $rows->filter($f)->sum('n');

        return [
            'total' => $sum(fn () => true),
            'unlocked_from_start' => $sum(fn ($r) => ! $r->locked),
            'waiting' => $sum(fn ($r) => $r->locked && $r->st === 'not_eligible' && ! $r->skipped),
            'eligible' => $sum(fn ($r) => $r->locked && in_array($r->st, ['pending', 'sent', 'failed'], true)),
            'pending' => $sum(fn ($r) => $r->st === 'pending'),
            'sent' => $sum(fn ($r) => $r->st === 'sent'),
            'failed' => $sum(fn ($r) => $r->st === 'failed'),
            'skipped' => $sum(fn ($r) => (bool) $r->skipped),
        ];
    }
}
