<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Jobs\SendQrReleaseBatch;
use App\Mail\QrReleaseMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use App\Services\Tickets\QrReleaseSandbox;
use App\Services\Tickets\QrReleaseService;
use App\Support\TicketQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventBus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** QR-release e-mails: eligibility, no duplicates, queue batches, failures, Test / Live modes, sandbox. */
class QrReleaseNotificationsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // The QR starts hidden (manual); release e-mails are LIVE unless a test says otherwise.
        $this->event = $this->newEvent('Gate Night', ['qr_visibility_mode' => 'manual', 'qr_release_mode' => 'live']);
    }

    private function newEvent(string $name, array $attrs = []): Event
    {
        $e = new Event();
        $e->forceFill(array_merge(['name' => $name, 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo',
            'description' => '', 'status' => 'active', 'requires_booking_approval' => false], $attrs))->save();

        return $e;
    }

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function show(?Event $e = null): void
    {
        app(\App\Services\Tickets\QrVisibilityService::class)->override($e ?? $this->event, null, 'show');
    }

    private function hide(?Event $e = null): void
    {
        app(\App\Services\Tickets\QrVisibilityService::class)->override($e ?? $this->event, null, 'hide');
    }

    /** Bulk guest-style tickets (no order): $holders = list of e-mails, one ticket each. */
    private function bulk(Event $e, array $holders, bool $locked = true, string $status = 'not_eligible'): void
    {
        $now = now();
        foreach (array_chunk($holders, 200, true) as $chunk) {
            $tickets = $issued = [];
            foreach ($chunk as $i => $email) {
                $num = (string) (7000000000 + random_int(0, 99999999) + $i * 100000000);
                $num = substr(str_pad($num, 11, '0'), 0, 11).str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $tickets[] = ['name' => $e->name.' - Guest VIP', 'source' => 'guest_list', 'price' => 0, 'status' => 'not_checked_in', 'ticket_number' => $num, 'holder_name' => 'Holder '.$i,
                    'holder_email' => $email, 'qr_payload' => TicketQr::newToken(), 'event_id' => $e->id, 'issued_at' => $now, 'created_at' => $now, 'updated_at' => $now];
                $issued[] = ['order_id' => null, 'order_item_id' => null, 'uuid' => (string) Str::uuid(), 'ticket_number' => $num, 'holder_name' => 'Holder '.$i, 'holder_email' => (string) $email,
                    'ticket_name' => $e->name.' - Guest VIP', 'ticket_price' => 0, 'seat_index' => 1, 'event_id' => $e->id, 'qr_locked_at_issue' => $locked, 'qr_notice_status' => $status,
                    'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('tickets')->insert($tickets);
            DB::table('issued_tickets')->insert($issued);
        }
    }

    private function n(string $status, ?Event $e = null): int
    {
        return IssuedTicket::where('event_id', ($e ?? $this->event)->id)->where('qr_notice_status', $status)->count();
    }

    private function order(array $holders, ?Event $event = null, string $buyerEmail = 'buyer@example.com'): Order
    {
        $buyer = User::factory()->create(['email' => $buyerEmail, 'email_verified_at' => now()]);
        $order = $this->makePendingOrder($buyer, $event ?? $this->event, count($holders));
        $order->customer->update(['email' => $buyerEmail]);
        foreach ($order->items()->orderBy('id')->get() as $i => $item) {
            $item->update(['holder_name' => $holders[$i][0], 'holder_email' => $holders[$i][1]]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return $order->fresh();
    }

    private function dash(IssuedTicket $t): Ticket
    {
        return Ticket::where('ticket_number', $t->ticket_number)->firstOrFail();
    }

    /** Recipients of everything actually sent through the (array) transport. */
    private function wire(): \Illuminate\Support\Collection
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress());
    }

    /* ------------------------------------------------------------ 0. the default is "nothing changed" */

    public function test_a_new_event_behaves_exactly_as_before_until_an_admin_changes_the_settings(): void
    {
        Mail::fake();
        Queue::fake();
        $e = Event::create(['name' => 'Plain Night', 'event_date' => now()->addDays(3)->toDateString(), 'event_time' => '20:00:00', 'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false])->fresh();

        $this->assertSame(['immediate', null, 'always', null, 'test', null], [$e->qr_visibility_mode, $e->qr_override, $e->entry_mode, $e->entry_override, $e->qr_release_mode, $e->qr_sandbox]);
        $this->assertTrue($e->qrIsVisible());      // the QR is visible, as before
        $this->assertTrue($e->entryIsOpen());      // the gates are open, as before
        $this->assertFalse($e->qrReleaseIsLive()); // no release e-mails

        $o = $this->order([['A', 'a@example.com']], $e);
        $t = $o->issuedTickets->first();
        $this->assertSame([false, 'not_eligible'], [(bool) $t->qr_locked_at_issue, $t->qr_notice_status]);
        $this->actingAs(User::find($o->user_id))->getJson(route('front.tickets.qr', $t))->assertJson(['state' => 'visible']);

        Artisan::call('tickets:release-qr');
        Queue::assertNothingPushed();
        Mail::assertNotSent(QrReleaseMail::class);
    }

    public function test_even_live_mode_sends_nothing_for_an_event_whose_qr_was_never_hidden(): void
    {
        Mail::fake();
        $e = $this->newEvent('Always Visible', ['qr_release_mode' => 'live']);   // live, but default visibility: tickets are never locked
        $this->order([['A', 'a@example.com']], $e);
        Artisan::call('tickets:release-qr');
        $this->hide($e);
        $this->show($e);
        Mail::assertNotSent(QrReleaseMail::class);
    }

    public function test_a_duplicated_event_starts_with_the_defaults_not_the_live_settings_of_the_original(): void
    {
        $this->event->forceFill(['qr_override' => 'hide', 'entry_mode' => 'manual', 'qr_sandbox' => ['visible' => true, 'tickets' => [], 'log' => []]])->save();
        $copy = app(\App\Services\Events\EventDuplicator::class)->duplicate($this->event->fresh())->fresh();

        $this->assertSame(['immediate', null, 'always', 'test', null], [$copy->qr_visibility_mode, $copy->qr_override, $copy->entry_mode, $copy->qr_release_mode, $copy->qr_sandbox]);
    }

    /* ------------------------------------------------------------ 1. who is eligible */

    public function test_a_ticket_issued_while_the_qr_is_hidden_is_marked_locked_and_one_issued_after_opening_is_not(): void
    {
        Mail::fake();
        $locked = $this->order([['A', 'a@example.com']]);
        $this->assertSame([true, 'not_eligible'], [(bool) $locked->issuedTickets->first()->qr_locked_at_issue, $locked->issuedTickets->first()->qr_notice_status]);
        $this->show();   // opens: the locked one is notified; one issued afterwards is not
        $open = $this->order([['B', 'b@example.com']], null, 'buyer2@example.com');
        $this->assertSame([false, 'not_eligible'], [(bool) $open->issuedTickets->first()->qr_locked_at_issue, $open->issuedTickets->first()->qr_notice_status]);
        $this->assertSame('sent', $locked->issuedTickets->first()->fresh()->qr_notice_status);
        $this->assertSame($this->event->id, $locked->issuedTickets->first()->event_id);
    }

    public function test_tickets_bought_after_the_qr_opened_never_get_a_release_email_but_see_their_qr(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['early@example.com']);
        $this->show();   // 3 PM: opens (live) → the early ticket is e-mailed
        Mail::assertSent(QrReleaseMail::class, 1);

        $late = $this->order([['Late', 'late@example.com']]);   // 5 PM purchase
        $t = $late->issuedTickets->first();
        $this->assertSame(['not_eligible', false], [$t->qr_notice_status, (bool) $t->qr_locked_at_issue]);
        Artisan::call('tickets:release-qr');
        Mail::assertSent(QrReleaseMail::class, 1);            // still only the early one
        Mail::assertNotSent(QrReleaseMail::class, fn ($m) => $m->hasTo('late@example.com'));
        $buyer = User::find($late->user_id);
        $this->actingAs($buyer)->getJson(route('front.tickets.qr', $t))->assertJson(['state' => 'visible']);   // unlocked immediately in My Tickets
    }

    public function test_the_release_state_of_old_tickets_is_not_eligible_by_default(): void
    {
        $this->bulk($this->event, ['old@example.com'], false);   // issued before this feature: locked_at_issue = false
        $this->show();
        Mail::fake();
        Artisan::call('tickets:release-qr');
        Mail::assertNothingSent();
        $this->assertSame(1, $this->n('not_eligible'));
    }

    /* ------------------------------------------------------------ 2. test mode never sends to customers */

    public function test_in_test_mode_nothing_is_marked_queued_or_sent_when_the_qr_opens(): void
    {
        Mail::fake();
        Queue::fake();
        $test = $this->newEvent('Test Night', ['qr_visibility_mode' => 'manual']);   // default mode = test
        $this->assertSame('test', $test->fresh()->qr_release_mode);
        $this->bulk($test, ['a@example.com', 'b@example.com']);

        $this->show($test);
        Artisan::call('tickets:release-qr');

        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        $this->assertSame(2, IssuedTicket::where('event_id', $test->id)->where('qr_notice_status', 'not_eligible')->count());
    }

    /* ------------------------------------------------------------ 3. live: queue, grouping, no repeats */

    public function test_show_in_live_mode_queues_a_background_batch_instead_of_sending_inline(): void
    {
        Queue::fake();
        Mail::fake();
        $this->bulk($this->event, ['a@example.com', 'b@example.com']);

        $this->show();

        Queue::assertPushed(SendQrReleaseBatch::class, 1);
        Mail::assertNothingSent();                              // the Show action itself sends nothing
        $this->assertSame(2, $this->n('pending'));
    }

    public function test_manual_show_sends_one_grouped_email_per_recipient_and_marks_sent(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['x@example.com', 'x@example.com', 'X@example.com', 'y@example.com']);

        $this->show();

        Mail::assertSent(QrReleaseMail::class, 2);
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('x@example.com') && count($m->items) === 3);
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('y@example.com') && count($m->items) === 1);
        $this->assertSame(4, $this->n('sent'));
        $this->assertNotNull(IssuedTicket::where('event_id', $this->event->id)->first()->qr_notice_sent_at);
    }

    public function test_hide_sends_nothing_and_hide_then_show_does_not_repeat_notified_tickets(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com']);
        $this->show();
        Mail::assertSent(QrReleaseMail::class, 1);

        $this->hide();
        Mail::assertSent(QrReleaseMail::class, 1);               // nothing on Hide
        $this->show();
        $this->hide();
        $this->show();
        Artisan::call('tickets:release-qr');
        Artisan::call('tickets:release-qr');
        Mail::assertSent(QrReleaseMail::class, 1);               // still exactly one

        $this->bulk($this->event, ['b@example.com']);            // a ticket issued while hidden... it is locked and waiting
        $this->hide();
        $this->assertSame(1, $this->n('sent'));
    }

    public function test_a_ticket_queued_while_the_qr_is_hidden_again_waits_and_is_sent_once_when_shown(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com']);
        $this->show();                                           // sent immediately (sync queue); add a fresh pending ticket then hide before its job runs
        $this->bulk($this->event, ['b@example.com']);
        IssuedTicket::where('holder_email', 'b@example.com')->update(['qr_notice_status' => 'pending']);
        $this->hide();
        SendQrReleaseBatch::dispatchSync($this->event->id);
        Mail::assertNotSent(QrReleaseMail::class, fn ($m) => $m->hasTo('b@example.com'));
        $this->assertSame(1, $this->n('pending'));

        $this->show();
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('b@example.com'));
        $this->assertSame(0, $this->n('pending'));
    }

    public function test_scheduled_release_sends_when_the_reveal_time_arrives_and_the_scheduler_repeating_never_duplicates(): void
    {
        Mail::fake();
        $this->event->forceFill(['qr_visibility_mode' => 'scheduled', 'qr_reveal_minutes_before' => 24 * 60])->save();
        $this->bulk($this->event, ['a@example.com', 'b@example.com']);

        Artisan::call('tickets:release-qr');                     // too early
        Mail::assertNothingSent();

        $this->travelTo($this->event->fresh()->qrRevealsAt()->addMinute());
        for ($i = 0; $i < 4; $i++) {                             // the scheduler ticks every minute
            Artisan::call('tickets:release-qr');
        }
        Mail::assertSent(QrReleaseMail::class, 2);
        $this->assertSame(2, $this->n('sent'));
    }

    /* ------------------------------------------------------------ 4. a thousand tickets, some already notified */

    public function test_a_thousand_tickets_in_batches_notify_only_the_pending_ones_once(): void
    {
        Mail::fake();
        $recipients = array_map(fn ($i) => 'user'.$i.'@example.com', range(1, 400));
        $all = [];
        for ($i = 0; $i < 1000; $i++) {
            $all[] = $recipients[$i % 400];                       // 2.5 tickets per recipient on average
        }
        $this->bulk($this->event, $all);
        // 250 tickets (the first 250 inserted) were already notified earlier.
        $sentIds = IssuedTicket::where('event_id', $this->event->id)->orderBy('id')->limit(250)->pluck('id');
        IssuedTicket::whereIn('id', $sentIds)->update(['qr_notice_status' => 'sent', 'qr_notice_sent_at' => '2020-01-01 00:00:00']);

        Queue::fake();
        $this->show();
        Queue::assertPushed(SendQrReleaseBatch::class, 1);        // one job, not 1000 mails inside the Show action
        $this->assertSame(750, $this->n('pending'));
        Mail::assertNothingSent();

        Queue::swap(app('queue'));                                // run for real (sync)
        $this->app->forgetInstance('queue');
        SendQrReleaseBatch::dispatchSync($this->event->id);       // each run handles 50 recipients and chains the next batch

        $this->assertSame(0, $this->n('pending'));
        $this->assertSame(1000, $this->n('sent'));
        $this->assertSame(250, IssuedTicket::whereIn('id', $sentIds)->where('qr_notice_sent_at', '2020-01-01 00:00:00')->count(), 'already-notified tickets keep their original sent time');

        // Every recipient with a pending ticket got exactly one e-mail containing only their pending tickets.
        $pendingByRecipient = IssuedTicket::where('event_id', $this->event->id)->whereNotIn('id', $sentIds)->get()->groupBy(fn ($t) => $t->holder_email)->map->count();
        $sent = Mail::sent(QrReleaseMail::class);
        $this->assertSame($pendingByRecipient->count(), $sent->count());
        $this->assertSame($pendingByRecipient->sum(), $sent->sum(fn ($m) => count($m->items)));
        $this->assertSame($sent->count(), $sent->map(fn ($m) => $m->to[0]['address'])->unique()->count(), 'one e-mail per recipient');
    }

    public function test_the_summary_counts_every_group(): void
    {
        $this->bulk($this->event, ['a@example.com', 'b@example.com', 'c@example.com']);
        $this->bulk($this->event, ['d@example.com'], false);     // unlocked from the start
        IssuedTicket::where('holder_email', 'a@example.com')->update(['qr_notice_status' => 'sent']);
        IssuedTicket::where('holder_email', 'b@example.com')->update(['qr_notice_status' => 'failed']);

        $s = app(QrReleaseService::class)->summary($this->event);
        $this->assertSame(['total' => 4, 'unlocked_from_start' => 1, 'waiting' => 1, 'eligible' => 2, 'pending' => 0, 'sent' => 1, 'failed' => 1, 'skipped' => 0], $s);
    }

    /* ------------------------------------------------------------ 5. failures and safe retry */

    public function test_failed_sends_are_recorded_and_only_the_failed_ones_are_retried(): void
    {
        $this->bulk($this->event, ['ok1@example.com', 'bad@example.com', 'ok2@example.com']);
        $bad = true;
        EventBus::listen(MessageSending::class, function (MessageSending $e) use (&$bad) {
            if ($bad && $e->message->getTo()[0]->getAddress() === 'bad@example.com') {
                throw new \RuntimeException('SMTP down');
            }
        });

        $this->show();
        $this->assertSame(['sent' => 2, 'failed' => 1], ['sent' => $this->n('sent'), 'failed' => $this->n('failed')]);
        $f = IssuedTicket::where('holder_email', 'bad@example.com')->first();
        $this->assertStringContainsString('SMTP down', $f->qr_notice_error);
        $this->assertSame(1, $f->qr_notice_attempts);
        $this->assertSame(['ok1@example.com', 'ok2@example.com'], $this->wire()->sort()->values()->all());

        Artisan::call('tickets:release-qr');                      // the scheduler does not retry failures by itself
        $this->assertSame(1, $this->n('failed'));

        $bad = false;
        $admin = $this->staff(['events.qr-notifications.manage']);
        $this->actingAs($admin)->post(route('admin.events.qr-release.retry', $this->event))->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, $this->n('sent'));
        $this->assertSame(['bad@example.com', 'ok1@example.com', 'ok2@example.com'], $this->wire()->sort()->values()->all(), 'the two that already succeeded were not mailed again');
        $this->assertSame(2, IssuedTicket::where('holder_email', 'bad@example.com')->first()->qr_notice_attempts);
    }

    public function test_a_worker_that_died_mid_send_never_causes_a_blind_resend(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com', 'b@example.com']);
        $this->show();                                           // both sent
        $this->bulk($this->event, ['c@example.com', 'd@example.com']);
        // c: a worker claimed it 20 minutes ago and died (did the mail leave? unknown). d: claimed a moment ago by a live worker.
        IssuedTicket::where('holder_email', 'c@example.com')->update(['qr_notice_status' => 'pending', 'qr_notice_claim' => 'dead-worker', 'qr_notice_claimed_at' => now()->subMinutes(20)]);
        IssuedTicket::where('holder_email', 'd@example.com')->update(['qr_notice_status' => 'pending', 'qr_notice_claim' => 'live-worker', 'qr_notice_claimed_at' => now()->subSeconds(5)]);
        Mail::fake();

        app(QrReleaseService::class)->process($this->event);
        SendQrReleaseBatch::dispatchSync($this->event->id);

        Mail::assertNothingSent();                               // neither is mailed: c needs an admin's check, d belongs to a running worker
        $c = IssuedTicket::where('holder_email', 'c@example.com')->first();
        $this->assertSame('failed', $c->qr_notice_status);
        $this->assertStringContainsString('delivery unknown', $c->qr_notice_error);
        $this->assertSame('pending', IssuedTicket::where('holder_email', 'd@example.com')->first()->qr_notice_status);
    }

    public function test_two_workers_cannot_mail_the_same_recipient_or_ticket(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com', 'a@example.com']);
        $this->event->forceFill(['qr_override' => 'show'])->save();
        IssuedTicket::where('event_id', $this->event->id)->update(['qr_notice_status' => 'pending']);

        $lock = Cache::lock('qr-release:'.$this->event->id.':'.sha1('a@example.com'), 60);
        $this->assertTrue($lock->get());                          // another worker is sending to a@ right now
        SendQrReleaseBatch::dispatchSync($this->event->id);
        Mail::assertNothingSent();
        $lock->release();

        SendQrReleaseBatch::dispatchSync($this->event->id);
        SendQrReleaseBatch::dispatchSync($this->event->id);       // a duplicate job: nothing left to claim
        Mail::assertSent(QrReleaseMail::class, 1);
    }

    public function test_an_address_blocked_by_the_staging_mail_allowlist_is_a_failure_not_a_sent(): void
    {
        config(['notifications.mail_allowlist' => ['@tkthouse.com']]);
        Mail::fake();
        $this->bulk($this->event, ['x@customer.com', 'qa@tkthouse.com']);

        $this->show();

        $this->assertSame('sent', IssuedTicket::where('holder_email', 'qa@tkthouse.com')->first()->qr_notice_status);
        $b = IssuedTicket::where('holder_email', 'x@customer.com')->first();
        $this->assertSame('failed', $b->qr_notice_status);
        $this->assertStringContainsString('allowlist', $b->qr_notice_error);
    }

    /* ------------------------------------------------------------ 6. refunds, cancels, used */

    public function test_full_and_partial_refunds_canceled_and_used_tickets_are_not_mailed(): void
    {
        Mail::fake();
        $full = $this->order([['F', 'full@example.com']], null, 'b1@example.com');
        $partial = $this->order([['P1', 'p1@example.com'], ['P2', 'p2@example.com'], ['P3', 'p3@example.com']], null, 'b2@example.com');
        $ok = $this->order([['Ok', 'ok@example.com']], null, 'b3@example.com');

        $full->forceFill(['status' => 'refunded'])->save();
        $this->dash($full->issuedTickets->first())->update(['status' => 'canceled', 'cancellation_source' => 'refund']);
        $ts = $partial->issuedTickets->sortBy('id')->values();
        $this->dash($ts[0])->update(['status' => 'canceled', 'cancellation_source' => 'refund']);   // partial refund
        $this->dash($ts[1])->update(['status' => 'checked_in']);                                      // used
        $partial->forceFill(['status' => 'partially_refunded'])->save();

        $this->show();

        $recipients = Mail::sent(QrReleaseMail::class)->map(fn ($m) => $m->to[0]['address'])->sort()->values()->all();
        $this->assertSame(['ok@example.com', 'p3@example.com'], $recipients);
        $this->assertSame('not_eligible', $full->issuedTickets->first()->fresh()->qr_notice_status);
    }

    public function test_a_ticket_refunded_after_it_was_queued_is_dropped_at_send_time(): void
    {
        Mail::fake();
        $o = $this->order([['R', 'r@example.com']]);
        IssuedTicket::where('order_id', $o->id)->update(['qr_notice_status' => 'pending']);
        $this->event->forceFill(['qr_override' => 'show'])->save();
        $o->forceFill(['status' => 'refunded'])->save();
        $this->dash($o->issuedTickets->first())->update(['status' => 'canceled', 'cancellation_source' => 'refund']);

        SendQrReleaseBatch::dispatchSync($this->event->id);

        Mail::assertNotSent(QrReleaseMail::class);
        $t = $o->issuedTickets->first()->fresh();
        $this->assertSame('not_eligible', $t->qr_notice_status);
        $this->assertStringContainsString('No longer eligible', $t->qr_notice_error);
    }

    /* ------------------------------------------------------------ 7. who gets what, and what the e-mail contains */

    public function test_each_holder_gets_only_their_tickets_and_the_e_mail_exposes_no_order_payment_or_other_tickets(): void
    {
        Mail::fake();
        $o = $this->order([['Ahmed', 'ahmed@example.com'], ['Mohamed', 'mohamed@example.com'], ['Omar', 'omar@example.com'], ['Omar Two', 'omar@example.com']], null, 'ahmed@example.com');

        $this->show();

        Mail::assertSent(QrReleaseMail::class, 3);
        foreach (['ahmed@example.com' => ['Ahmed'], 'mohamed@example.com' => ['Mohamed'], 'omar@example.com' => ['Omar', 'Omar Two']] as $to => $names) {
            Mail::assertSent(QrReleaseMail::class, function (QrReleaseMail $m) use ($to, $names, $o) {
                if (! $m->hasTo($to)) {
                    return false;
                }
                $this->assertSame($names, array_column($m->items, 'holder'));
                $html = html_entity_decode($m->render());
                $this->assertStringContainsStringIgnoringCase('View your tickets', $html);
                $this->assertStringContainsString('Gate Night', $html);
                $this->assertStringContainsString('VIP', $html.'VIP');
                foreach ([$o->order_number, 'INV-', 'Invoice', 'EGP', 'TEST EMAIL'] as $private) {
                    $this->assertStringNotContainsString($private, $html, "leaks $private");
                }
                foreach (array_diff(['Ahmed', 'Mohamed', 'Omar Two'], $names) as $other) {
                    $this->assertStringNotContainsString($other, $html, "shows $other's ticket");
                }
                $this->assertSame([], $m->rawAttachments === [] ? [] : array_filter($m->rawAttachments, fn ($a) => str_contains($a['name'] ?? '', '.pdf')));

                return true;
            });
        }
    }

    public function test_the_email_carries_a_separate_qr_png_per_ticket_that_matches_the_real_ticket(): void
    {
        $this->bulk($this->event, ['a@example.com', 'a@example.com']);
        $this->show();   // array transport: look at the real message
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $images = collect($email->getAttachments())->filter(fn ($p) => str_starts_with($p->getMediaType().'/'.$p->getMediaSubtype(), 'image/png'));
        $this->assertCount(2, $images, 'one embedded QR image per ticket');
        $this->assertSame(2, $images->map(fn ($p) => md5($p->getBody()))->unique()->count(), 'each QR is different');
        $this->assertStringNotContainsString('data:image', (string) $email->getHtmlBody());
    }

    public function test_the_scanner_still_rejects_refunded_used_and_closed_gate_tickets_whose_qr_was_e_mailed(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $scanner = $this->staff(['scanner.access']);
        $o = $this->order([['A', 'a@example.com'], ['B', 'b@example.com'], ['C', 'c@example.com']]);
        $this->show();   // e-mails sent with the real tokens
        $t = $o->issuedTickets->sortBy('id')->values();
        $lookup = fn (IssuedTicket $i) => $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $this->dash($i)->qr_payload, 'source' => 'camera']);

        $lookup($t[0])->assertOk();                               // the e-mailed QR works...
        $this->dash($t[1])->update(['status' => 'canceled', 'cancellation_source' => 'refund']);
        $lookup($t[1])->assertStatus(409);                        // ...unless refunded
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $this->dash($t[0])), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $this->dash($t[0])), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));
        $this->event->forceFill(['entry_mode' => 'manual'])->save();
        $lookup($t[2])->assertStatus(409)->assertJson(['code' => 'gates_closed']);   // the e-mailed QR does not open the gates early
    }

    /* ------------------------------------------------------------ 8. test mode / live mode controls */

    public function test_going_live_needs_the_permission_a_confirmation_and_a_choice(): void
    {
        $test = $this->newEvent('Mode Night', ['qr_visibility_mode' => 'manual']);
        $viewer = $this->staff(['events.update', 'events.qr-visibility.manage']);
        $this->actingAs($viewer)->post(route('admin.events.qr-release.activate', $test), ['choice' => 'skip', 'confirm_live' => 1])->assertForbidden();

        $admin = $this->staff(['events.qr-notifications.manage']);
        $this->actingAs($admin)->post(route('admin.events.qr-release.activate', $test), ['choice' => 'skip'])->assertSessionHasErrors('confirm_live');
        $this->actingAs($admin)->post(route('admin.events.qr-release.activate', $test), ['choice' => 'maybe', 'confirm_live' => 1])->assertSessionHasErrors('choice');
        $this->assertSame('test', $test->fresh()->qr_release_mode);

        $this->actingAs($admin)->post(route('admin.events.qr-release.activate', $test), ['choice' => 'notify', 'confirm_live' => 1])->assertRedirect();
        $e = $test->fresh();
        $this->assertSame(['live', $admin->id], [$e->qr_release_mode, $e->qr_release_activated_by]);
        $this->assertSame(1, Activity::where('subject_id', $test->id)->where('description', 'like', 'QR release e-mails set to LIVE%')->where('causer_id', $admin->id)->count());
    }

    public function test_activating_live_never_sends_old_notifications_suddenly_skip_choice(): void
    {
        Mail::fake();
        $test = $this->newEvent('Old Night', ['qr_visibility_mode' => 'manual']);          // test mode
        $this->bulk($test, ['a@example.com', 'b@example.com', 'b@example.com']);
        $this->show($test);                                                                   // opened while in test mode: nothing sent
        Mail::assertNothingSent();

        $svc = app(QrReleaseService::class);
        $p = $svc->preview($test);
        $this->assertSame([true, 3, 2], [$p['visible'], $p['tickets_now'], $p['recipients_now']]);   // 3 tickets / 2 recipients would be e-mailed

        $svc->activateLive($test, null, 'skip');
        Artisan::call('tickets:release-qr');
        Mail::assertNothingSent();                                                            // skipped on purpose: nobody is e-mailed at activation
        $this->assertSame(3, IssuedTicket::where('event_id', $test->id)->whereNotNull('qr_notice_skipped_at')->count());

        $this->hide($test);
        $this->show($test);                                                                   // hide / show later: skipped tickets stay skipped
        Artisan::call('tickets:release-qr');
        Mail::assertNothingSent();

        $this->bulk($test, ['new@example.com']);                                              // only a ticket that is locked from now on will be notified
        $this->hide($test);
        $this->show($test);
        Mail::assertSent(QrReleaseMail::class, 1);
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('new@example.com'));
    }

    public function test_activating_live_with_notify_sends_to_the_previewed_recipients_in_the_background(): void
    {
        Mail::fake();
        Queue::fake();
        $test = $this->newEvent('Notify Night', ['qr_visibility_mode' => 'manual']);
        $this->bulk($test, ['a@example.com', 'b@example.com']);
        $this->show($test);

        app(QrReleaseService::class)->activateLive($test, null, 'notify');

        Queue::assertPushed(SendQrReleaseBatch::class);
        Mail::assertNothingSent();                                                            // queued, not inline
        $this->assertSame(2, IssuedTicket::where('event_id', $test->id)->where('qr_notice_status', 'pending')->count());
    }

    public function test_activating_live_while_the_qr_is_hidden_sends_nothing_and_waits_for_the_reveal(): void
    {
        Mail::fake();
        $test = $this->newEvent('Hidden Night', ['qr_visibility_mode' => 'manual']);
        $this->bulk($test, ['a@example.com']);
        $svc = app(QrReleaseService::class);
        $this->assertSame([false, 0, 1], [$svc->preview($test)['visible'], $svc->preview($test)['tickets_now'], $svc->preview($test)['tickets_waiting']]);

        $svc->activateLive($test, null, 'skip');                                              // hidden now: nothing to skip
        Mail::assertNothingSent();
        $this->assertSame(0, IssuedTicket::where('event_id', $test->id)->whereNotNull('qr_notice_skipped_at')->count());

        $this->show($test);
        Mail::assertSent(QrReleaseMail::class, 1);
    }

    public function test_switching_back_to_test_pauses_sending_and_going_live_again_asks_again(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com']);
        $svc = app(QrReleaseService::class);
        $svc->switchToTest($this->event, null);

        $this->show();                                                                        // open while paused
        Artisan::call('tickets:release-qr');
        Mail::assertNothingSent();

        $this->assertSame(['tickets_now' => 1, 'recipients_now' => 1], \Illuminate\Support\Arr::only($svc->preview($this->event->fresh()), ['tickets_now', 'recipients_now']));
        $svc->activateLive($this->event->fresh(), null, 'skip');
        Mail::assertNothingSent();
    }

    public function test_pending_tickets_stay_pending_while_paused_and_failed_retry_needs_live(): void
    {
        Mail::fake();
        $this->bulk($this->event, ['a@example.com']);
        IssuedTicket::where('event_id', $this->event->id)->update(['qr_notice_status' => 'failed']);
        $svc = app(QrReleaseService::class);
        $svc->switchToTest($this->event, null);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->retryFailed($this->event->fresh(), null);
    }

    public function test_the_admin_card_shows_mode_summary_preview_and_the_right_controls(): void
    {
        $this->bulk($this->event, ['a@example.com', 'b@example.com']);
        IssuedTicket::where('holder_email', 'a@example.com')->update(['qr_notice_status' => 'failed', 'qr_notice_error' => 'SMTP down']);
        $admin = $this->staff(['events.update', 'events.qr-notifications.manage']);

        $this->actingAs($admin)->get(route('admin.events.edit', $this->event))->assertOk()->assertSee('QR Release e-mails')->assertSee('LIVE')
            ->assertSee('Retry failed notifications (1)')->assertSee('SMTP down')->assertSee('Notified (Sent)')->assertDontSee('Send sample e-mail');

        $this->event->forceFill(['qr_release_mode' => 'test'])->save();
        $this->actingAs($admin)->get(route('admin.events.edit', $this->event))->assertOk()->assertSee('TEST MODE')->assertSee('Send sample e-mail')->assertSee('Show QR (test)')
            ->assertSee('Preview')->assertSee('Go Live')->assertDontSee('Bulk send');

        $noPerm = $this->staff(['events.update']);
        $this->actingAs($noPerm)->get(route('admin.events.edit', $this->event))->assertOk()->assertDontSee('QR Release e-mails');
    }

    /* ------------------------------------------------------------ 9. the test sandbox */

    public function test_the_sandbox_runs_the_same_rule_on_sample_tickets_without_touching_anything_real(): void
    {
        Mail::fake();
        $test = $this->newEvent('Sandbox Night', ['qr_visibility_mode' => 'manual']);
        $this->bulk($test, ['real@example.com']);
        $realBefore = IssuedTicket::where('event_id', $test->id)->first()->only(['qr_notice_status', 'qr_notice_sent_at', 'qr_locked_at_issue']);
        $visBefore = $test->only(['qr_override', 'qr_visibility_mode']);
        $sb = app(QrReleaseSandbox::class);
        $admin = $this->staff(['events.qr-notifications.manage']);

        $sb->reset($test);
        $s = $sb->show($test, $admin, 'qa@mycompany.test');
        $this->assertSame(['sent', 'sent', 'sent'], array_column($s['tickets'], 'status'));
        Mail::assertSent(QrReleaseMail::class, 2);                                            // the sample buyer's 2 tickets in one e-mail + the other sample holder
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('qa@mycompany.test') && $m->test === true);
        Mail::assertNotSent(QrReleaseMail::class, fn ($m) => $m->hasTo('real@example.com'));

        $sb->hide($test);
        $sb->show($test, $admin, 'qa@mycompany.test');                                        // hide → show: nothing repeats
        Mail::assertSent(QrReleaseMail::class, 2);

        $s = $sb->addLateTicket($test);                                                       // bought after open: unlocked, no release e-mail
        $this->assertSame([false, 'not_eligible'], [$s['tickets'][3]['locked_at_issue'], $s['tickets'][3]['status']]);
        $sb->hide($test);
        $s = $sb->addLateTicket($test);
        $s = $sb->show($test, $admin, 'qa@mycompany.test');                                   // locked-at-issue sample → notified now, once
        Mail::assertSent(QrReleaseMail::class, 3);

        // Nothing real changed: tickets, notification state, event visibility.
        $this->assertSame($realBefore, IssuedTicket::where('event_id', $test->id)->first()->only(['qr_notice_status', 'qr_notice_sent_at', 'qr_locked_at_issue']));
        $this->assertSame($visBefore, $test->fresh()->only(['qr_override', 'qr_visibility_mode']));
        $this->assertFalse($test->fresh()->qrIsVisible());
    }

    public function test_sandbox_failure_and_retry_only_retry_the_failed_samples(): void
    {
        Mail::fake();
        $test = $this->newEvent('Sandbox Fail', ['qr_visibility_mode' => 'manual']);
        $sb = app(QrReleaseSandbox::class);
        $admin = $this->staff(['events.qr-notifications.manage']);

        $s = $sb->show($test, $admin, 'qa@mycompany.test', true);
        $this->assertSame(['failed', 'failed', 'failed'], array_column($s['tickets'], 'status'));
        Mail::assertNothingSent();
        $s = $sb->retryFailed($test, $admin, 'qa@mycompany.test');
        $this->assertSame(['sent', 'sent', 'sent'], array_column($s['tickets'], 'status'));
        Mail::assertSent(QrReleaseMail::class, 2);
    }

    public function test_the_test_email_is_clearly_marked_uses_sample_data_and_its_qr_is_not_valid_for_entry(): void
    {
        $test = $this->newEvent('Sample Night', ['qr_visibility_mode' => 'manual']);
        $admin = $this->staff(['events.qr-notifications.manage', 'scanner.access']);
        app(QrReleaseSandbox::class)->sendSample($test, 'qa@mycompany.test', $admin);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $m = $messages[0]->getOriginalMessage();
        $this->assertStringStartsWith('[TEST] ', $m->getSubject());
        $this->assertStringContainsString('TEST EMAIL', (string) $m->getHtmlBody());
        $this->assertStringContainsString('NOT VALID FOR ENTRY', (string) $m->getHtmlBody());
        $this->assertStringContainsString('Test Holder 1', (string) $m->getHtmlBody());

        // The sample QR payload matches no ticket: the scanner cannot find it.
        $this->actingAs($admin)->postJson(route('admin.tickets.scanner.lookup'), ['code' => 'TEST-NOT-VALID-1', 'source' => 'camera'])->assertStatus(404);
    }

    public function test_test_emails_cannot_be_sent_to_real_attendees_only_to_the_admin_or_outside_addresses(): void
    {
        $test = $this->newEvent('Safe Night', ['qr_visibility_mode' => 'manual']);
        $o = $this->order([['Real Holder', 'holder@real.com']], $test, 'buyer@real.com');
        $admin = $this->staff(['events.qr-notifications.manage']);
        $sb = app(QrReleaseSandbox::class);
        Mail::fake();

        foreach (['holder@real.com', 'buyer@real.com', 'HOLDER@real.com'] as $bad) {
            try {
                $sb->sendSample($test, $bad, $admin);
                $this->fail("sent a test e-mail to $bad");
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('test_email', $e->errors());
            }
        }
        Mail::assertNothingSent();

        $sb->sendSample($test, $admin->email, $admin);                                          // own address: fine
        $sb->sendSample($test, 'qa@internal.test', $admin);
        Mail::assertSent(QrReleaseMail::class, 2);
    }

    public function test_the_sandbox_controller_validates_input_and_is_permission_gated(): void
    {
        Mail::fake();
        $test = $this->newEvent('Ctl Night', ['qr_visibility_mode' => 'manual']);
        $none = $this->staff(['events.update']);
        $this->actingAs($none)->post(route('admin.events.qr-release.sandbox', $test), ['action' => 'show'])->assertForbidden();

        $admin = $this->staff(['events.qr-notifications.manage']);
        $this->actingAs($admin)->post(route('admin.events.qr-release.sandbox', $test), ['action' => 'sample'])->assertSessionHasErrors('test_email');
        $this->actingAs($admin)->post(route('admin.events.qr-release.sandbox', $test), ['action' => 'boom'])->assertSessionHasErrors('action');
        $this->actingAs($admin)->post(route('admin.events.qr-release.sandbox', $test), ['action' => 'sample', 'test_email' => 'qa@internal.test'])->assertRedirect()->assertSessionHas('success');
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('qa@internal.test') && $m->test);
    }

    /* ------------------------------------------------------------ 10. QR e-mail ↔ existing rules */

    public function test_buyers_and_holders_are_notified_by_holder_address_and_a_buyer_bought_for_others_gets_nothing_extra(): void
    {
        Mail::fake();
        $this->order([['Buyer Self', 'buyer@example.com'], ['Friend', 'friend@example.com']]);   // buyer@example.com bought both
        $this->show();

        Mail::assertSent(QrReleaseMail::class, 2);
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('buyer@example.com') && array_column($m->items, 'holder') === ['Buyer Self']);
        Mail::assertSent(QrReleaseMail::class, fn ($m) => $m->hasTo('friend@example.com') && array_column($m->items, 'holder') === ['Friend']);
    }
}
