<?php

namespace App\Services\Tickets;

use App\Mail\QrReleaseMail;
use App\Models\Customer;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\User;
use App\Support\MailRetry;
use App\Support\TicketQr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Test Mode playground for QR-release e-mails. It runs the same rule (QrReleaseService::shouldBecomePending) on a handful of SAMPLE tickets
 * kept in `events.qr_sandbox`; it never reads or changes a real ticket, never changes the real QR visibility or gate, and the only
 * e-mails it can send go to a test address chosen by the admin, marked TEST, with QR codes that are not valid for entry.
 */
class QrReleaseSandbox
{
    public function state(Event $event): array
    {
        return $event->qr_sandbox ?: $this->fresh();
    }

    public function reset(Event $event): array
    {
        return $this->save($event, $this->fresh());
    }

    /** Sample "Show QR": every waiting sample ticket becomes pending, then is "sent" (grouped per recipient) — to the test address if one is given. */
    public function show(Event $event, ?User $by, ?string $testEmail, bool $simulateFailure = false): array
    {
        $s = $this->state($event);
        $s['visible'] = true;
        $this->log($s, 'Show QR (test): sample QR is now visible.');

        foreach ($s['tickets'] as &$t) {
            if (QrReleaseService::shouldBecomePending($t['status'], $t['locked_at_issue'], true)) {
                $t['status'] = IssuedTicket::NOTICE_PENDING;
            }
        }
        unset($t);

        $this->deliver($event, $s, $testEmail, $by, $simulateFailure);

        return $this->save($event, $s);
    }

    public function hide(Event $event): array
    {
        $s = $this->state($event);
        $s['visible'] = false;
        $this->log($s, 'Hide QR (test): hidden. No e-mail is ever sent when hiding.');

        return $this->save($event, $s);
    }

    /** A sample ticket "bought" now: if the QR is already visible it gets no release e-mail; if hidden it will on the next Show. */
    public function addLateTicket(Event $event): array
    {
        $s = $this->state($event);
        $n = count($s['tickets']) + 1;
        $s['tickets'][] = ['id' => $n, 'holder' => 'Test Late Buyer '.$n, 'email' => 'late'.$n.'@test.invalid', 'type' => 'General Admission', 'locked_at_issue' => ! $s['visible'], 'status' => 'not_eligible', 'attempts' => 0, 'error' => null];
        $this->log($s, 'Sample ticket bought '.($s['visible'] ? 'AFTER the QR opened: unlocked from the start, so no release e-mail (booking confirmation only).' : 'while the QR is hidden: it will be notified when the QR opens.'));

        return $this->save($event, $s);
    }

    public function retryFailed(Event $event, ?User $by, ?string $testEmail): array
    {
        $s = $this->state($event);
        $n = 0;
        foreach ($s['tickets'] as &$t) {
            if ($t['status'] === IssuedTicket::NOTICE_FAILED) {
                $t['status'] = IssuedTicket::NOTICE_PENDING;
                $t['error'] = null;
                $n++;
            }
        }
        unset($t);
        $this->log($s, "Retry failed (test): $n ticket(s) retried; sent tickets are untouched.");
        $this->deliver($event, $s, $testEmail, $by, false);

        return $this->save($event, $s);
    }

    /** One sample e-mail to the test address, whatever the sandbox state. */
    public function sendSample(Event $event, string $email, ?User $by): void
    {
        $this->assertSafeAddress($event, $email, $by);
        $this->throttle($by);
        $this->mail($event, $email, [['id' => 1, 'holder' => 'Test Holder 1', 'type' => 'VIP'], ['id' => 2, 'holder' => 'Test Holder 2', 'type' => 'VIP']], 'Sample e-mail with two tickets for one recipient.');
    }

    /** Refuses addresses that belong to real attendees of this event (except the admin's own), so a test can never reach a customer. */
    public function assertSafeAddress(Event $event, string $email, ?User $by): string
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['test_email' => 'Enter a valid test e-mail address.']);
        }
        if ($by && mb_strtolower((string) $by->email) === $email) {
            return $email;
        }

        $isHolder = IssuedTicket::query()->where('event_id', $event->id)->whereRaw('LOWER(holder_email) = ?', [$email])->exists();
        $isBuyer = Customer::query()->whereRaw('LOWER(email) = ?', [$email])->whereIn('id', \App\Models\Order::query()->select('customer_id')->whereIn('id', IssuedTicket::query()->where('event_id', $event->id)->select('order_id')))->exists();
        if ($isHolder || $isBuyer) {
            throw ValidationException::withMessages(['test_email' => 'That address belongs to a real ticket holder or buyer of this event. Use your own or an internal address for tests.']);
        }

        return $email;
    }

    private function deliver(Event $event, array &$s, ?string $testEmail, ?User $by, bool $simulateFailure): void
    {
        $pending = collect($s['tickets'])->where('status', IssuedTicket::NOTICE_PENDING)->groupBy('email');
        if ($pending->isEmpty()) {
            $this->log($s, 'Nothing to send: no sample ticket is waiting (tickets already notified are never notified again).');

            return;
        }

        $address = $testEmail ? $this->assertSafeAddress($event, $testEmail, $by) : null;
        if ($address) {
            $this->throttle($by);
        }

        foreach ($pending as $recipient => $group) {
            $ids = $group->pluck('id')->all();
            $error = $simulateFailure ? 'Simulated failure' : ($address ? $this->mail($event, $address, $group->all(), 'Sample for recipient '.$recipient.' ('.count($ids).' ticket'.(count($ids) > 1 ? 's' : '').')') : null);
            foreach ($s['tickets'] as &$t) {
                if (in_array($t['id'], $ids, true)) {
                    $t['status'] = $error === null ? IssuedTicket::NOTICE_SENT : IssuedTicket::NOTICE_FAILED;
                    $t['error'] = $error;
                    $t['attempts'] = ($t['attempts'] ?? 0) + 1;
                }
            }
            unset($t);
            $this->log($s, ($error === null ? 'Sent' : 'FAILED').' (test): '.count($ids).' ticket(s) for '.$recipient.' in ONE e-mail'.($address ? " → $address" : ' (no test address: simulated only)').($error ? " — $error" : '').'.');
        }
    }

    /** @return string|null error */
    private function mail(Event $event, string $to, array $tickets, string $note): ?string
    {
        $items = array_map(fn ($t) => ['holder' => $t['holder'], 'type' => $t['type'], 'uuid' => null, 'png' => TicketQr::png('TEST-NOT-VALID-'.$t['id'])], array_values($tickets));

        if (! \App\Support\MailAllowlist::allows($to)) {
            return 'Blocked by the mail safety allowlist (NOTIFY_MAIL_ALLOWLIST): this address cannot receive mail from this environment.';
        }

        return MailRetry::send(fn () => Mail::to($to)->send(new QrReleaseMail($event, $items, url('/account/tickets'), true, $note)), ['event_id' => $event->id, 'mail_type' => 'qr_release_test']);
    }

    private function throttle(?User $by): void
    {
        $key = 'qr-test-mail:'.($by?->id ?? 0);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['test_email' => 'Too many test e-mails this hour. Try again later.']);
        }
        RateLimiter::hit($key, 3600);
    }

    private function fresh(): array
    {
        $t = fn (int $id, string $h, string $e, string $type) => ['id' => $id, 'holder' => $h, 'email' => $e, 'type' => $type, 'locked_at_issue' => true, 'status' => 'not_eligible', 'attempts' => 0, 'error' => null];

        return ['visible' => false, 'log' => ['Sandbox ready: 3 sample tickets, all locked. Nothing here touches real tickets.'], 'tickets' => [
            $t(1, 'Test Buyer', 'buyer@test.invalid', 'VIP'), $t(2, 'Test Buyer (guest)', 'buyer@test.invalid', 'VIP'), $t(3, 'Test Holder', 'holder@test.invalid', 'General Admission'),
        ]];
    }

    private function log(array &$s, string $line): void
    {
        $s['log'] = array_slice([...($s['log'] ?? []), now()->format('H:i:s').' '.$line], -15);
    }

    private function save(Event $event, array $s): array
    {
        $event->forceFill(['qr_sandbox' => $s])->save();

        return $s;
    }
}
