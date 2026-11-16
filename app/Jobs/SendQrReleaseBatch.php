<?php

namespace App\Jobs;

use App\Mail\QrReleaseMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Support\MailAllowlist;
use App\Support\MailRetry;
use App\Support\TicketQr;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends the QR-release e-mails of one event in the background, a batch of recipients at a time, then queues itself again until nothing is
 * pending. Safe to run twice at once or to be killed half way: every ticket is *claimed* atomically before its recipient is e-mailed (so two
 * jobs never mail the same ticket), one e-mail carries all of a recipient's tickets, and a lock per recipient prevents parallel sends to them.
 */
class SendQrReleaseBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RECIPIENTS_PER_BATCH = 50;

    public int $timeout = 240;
    public int $tries = 1;   // a re-run would be harmless (claims), but a crashed run is recovered by the stale-claim rule, not by blind retries

    public function __construct(public int $eventId)
    {
    }

    public function handle(): void
    {
        $event = Event::query()->find($this->eventId);
        if (! $event || ! $event->qrReleaseIsLive() || ! $event->qrIsVisible()) {
            return;   // paused (Test mode) or hidden again: tickets simply stay pending
        }

        $base = fn () => IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)->whereNull('qr_notice_claim');

        // Tickets that cannot be e-mailed at all are marked failed (visible to the admin) instead of looping forever.
        $base()->where(fn ($q) => $q->whereNull('holder_email')->orWhere('holder_email', ''))
            ->update(['qr_notice_status' => IssuedTicket::NOTICE_FAILED, 'qr_notice_error' => 'No e-mail address for this ticket.', 'qr_notice_attempts' => DB::raw('qr_notice_attempts + 1')]);

        $recipients = $base()->selectRaw('LOWER(holder_email) as email, MIN(id) as first_id')->groupBy(DB::raw('LOWER(holder_email)'))
            ->orderBy('first_id')->limit(self::RECIPIENTS_PER_BATCH)->pluck('email');

        $handled = 0;
        foreach ($recipients as $email) {
            $handled += $this->sendTo($event, (string) $email) ? 1 : 0;
        }

        // Chain the next batch only if this one made progress; if everything left is locked by other workers, the scheduler picks it up later.
        if ($handled > 0 && $base()->exists()) {
            static::dispatch($event->id)->delay(now()->addSeconds(2));   // next batch; never one giant job
        }
    }

    /** @return bool whether this call claimed tickets for the recipient */
    private function sendTo(Event $event, string $email): bool
    {
        $lock = Cache::lock('qr-release:'.$event->id.':'.sha1($email), 120);
        if (! $lock->get()) {
            return false;   // another worker is sending to this recipient right now
        }

        try {
            $claim = (string) Str::uuid();
            $claimed = IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', IssuedTicket::NOTICE_PENDING)->whereNull('qr_notice_claim')
                ->whereRaw('LOWER(holder_email) = ?', [$email])
                ->update(['qr_notice_claim' => $claim, 'qr_notice_claimed_at' => now()]);
            if ($claimed === 0) {
                return false;
            }

            $tickets = IssuedTicket::query()->where('qr_notice_claim', $claim)->with(['dashboardTicket', 'order'])->orderBy('id')->get();

            // Re-check at the moment of sending: a ticket refunded / canceled / used since it was queued is dropped, not mailed.
            [$valid, $dropped] = $tickets->partition(fn (IssuedTicket $t) => $this->stillValid($t));
            if ($dropped->isNotEmpty()) {
                IssuedTicket::query()->whereIn('id', $dropped->pluck('id'))->update(['qr_notice_status' => IssuedTicket::NOTICE_NOT_ELIGIBLE, 'qr_notice_claim' => null, 'qr_notice_error' => 'No longer eligible (canceled, refunded or used).']);
            }
            if ($valid->isEmpty()) {
                return true;
            }

            $items = $valid->map(fn (IssuedTicket $t) => [
                'holder' => (string) $t->holder_name, 'type' => $t->ticketTypeLabel(), 'uuid' => $t->uuid,
                'png' => TicketQr::png($t->qrPayload()),
            ])->all();
            $url = $valid->count() === 1 ? route('front.tickets.show', $valid->first()) : route('front.account.tickets', ['when' => 'upcoming']);

            $error = ! MailAllowlist::allows($email)
                ? 'Not delivered: blocked by the mail safety allowlist (NOTIFY_MAIL_ALLOWLIST).'
                : MailRetry::send(fn () => Mail::to($email)->send(new QrReleaseMail($event, $items, $url)), ['event_id' => $event->id, 'recipient' => $email, 'mail_type' => 'qr_release']);

            IssuedTicket::query()->where('qr_notice_claim', $claim)->update($error === null
                ? ['qr_notice_status' => IssuedTicket::NOTICE_SENT, 'qr_notice_sent_at' => now(), 'qr_notice_claim' => null, 'qr_notice_error' => null, 'qr_notice_attempts' => DB::raw('qr_notice_attempts + 1')]
                : ['qr_notice_status' => IssuedTicket::NOTICE_FAILED, 'qr_notice_claim' => null, 'qr_notice_error' => $error, 'qr_notice_attempts' => DB::raw('qr_notice_attempts + 1')]);

            return true;
        } finally {
            $lock->release();
        }
    }

    private function stillValid(IssuedTicket $t): bool
    {
        $dash = $t->dashboardTicket;

        return $dash !== null && ! in_array($dash->status, ['canceled', 'checked_in'], true)
            && ($t->order_id === null || in_array((string) $t->order?->status, ['paid', 'partially_refunded'], true));
    }
}
