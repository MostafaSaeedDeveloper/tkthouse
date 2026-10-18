<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Checkout\CheckoutSelection;
use App\Services\Checkout\OrderPlacementService;
use App\Services\Events\EventLifecycle;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Refunds\RefundService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Events with history can only be archived; archiving never disturbs sold tickets; permanent delete is refused whenever history exists. */
class EventLifecycleTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['payments.fake.enabled' => true]);
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $perms = ['events.view', 'events.create', 'events.update', 'events.delete', 'events.archive', 'events.restore', 'scanner.access', 'payments.refunds.create', 'payments.refunds.view', 'payments.transactions.view', 'orders.view'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    private function lifecycle(): EventLifecycle
    {
        return app(EventLifecycle::class);
    }

    /** An event with one ticket type and (optionally) one paid, issued order. */
    private function eventWithSale(bool $sale = true): array
    {
        $event = $this->makeEvent(['name' => 'Archive Night']);
        $ticket = $this->makeTicket($event, ['name' => 'GA', 'price' => 500]);
        if (! $sale) {
            return [$event, $ticket, null];
        }
        $order = $this->makePendingOrder($this->admin, $event, 2, 500);
        $order->items()->update(['event_ticket_id' => $ticket->id, 'ticket_name' => $event->name.' - GA']);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return [$event, $ticket, $order->fresh()];
    }

    private function attemptFor(Order $order, string $status = 'paid'): PaymentAttempt
    {
        return PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => $order->total_amount, 'currency' => 'EGP', 'status' => $status, 'transaction_id' => 'T'.random_int(1000, 9999),
            'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => $status === 'paid' ? now() : null]);
    }

    private function counts(): array
    {
        return [Event::count(), EventTicket::count(), DB::table('order_items')->count(), Order::count(), IssuedTicket::count(), Ticket::count(), PaymentAttempt::count(), PaymentRefund::count(), ScanLog::count()];
    }

    /* ------------------------------------------------------------------ permanent delete */

    public function test_an_event_with_any_kind_of_history_cannot_be_deleted_and_nothing_is_lost(): void
    {
        $cases = [
            'order items by event id' => fn (Event $e, EventTicket $t) => $this->makePendingOrder($this->admin, $e, 1),
            'order item linked only by ticket-type id (event id lost)' => function (Event $e, EventTicket $t) {
                $o = $this->makePendingOrder($this->admin, $e, 1);
                $o->items()->update(['event_id' => null, 'event_ticket_id' => $t->id, 'ticket_name' => 'something else']);
            },
            'order item known only by its text (event id lost)' => function (Event $e, EventTicket $t) {
                $o = $this->makePendingOrder($this->admin, $e, 1);
                $o->items()->update(['event_id' => null, 'ticket_name' => $e->name.' - GA']);
            },
            'guest-list attendee ticket (no order)' => fn (Event $e, EventTicket $t) => Ticket::create(['event_id' => $e->id, 'name' => $e->name.' - Guest VIP', 'source' => 'guest_list', 'status' => 'not_checked_in', 'ticket_number' => 'GL-1', 'qr_payload' => 'GL-1', 'issued_at' => now()]),
            'QR ticket known only by name' => fn (Event $e, EventTicket $t) => Ticket::create(['event_id' => null, 'name' => $e->name.' - GA', 'status' => 'checked_in', 'ticket_number' => 'OLD-1', 'qr_payload' => 'OLD-1', 'issued_at' => now()]),
            'scanner log' => fn (Event $e, EventTicket $t) => ScanLog::create(['event_id' => $e->id, 'event_name' => $e->name, 'ticket_number' => 'X', 'action' => 'lookup_success', 'scanned_at' => now()]),
            'scanner log known only by event name' => fn (Event $e, EventTicket $t) => ScanLog::create(['event_id' => null, 'event_name' => $e->name, 'ticket_number' => 'X', 'action' => 'lookup_success', 'scanned_at' => now()]),
            'payment attempt of an order of the event' => function (Event $e, EventTicket $t) {
                $this->attemptFor($this->makePendingOrder($this->admin, $e, 1), 'initiated');
            },
        ];

        foreach ($cases as $label => $make) {
            $event = $this->makeEvent(['name' => 'Delete Test '.md5($label)]);
            $ticket = $this->makeTicket($event);
            $make($event, $ticket);
            $before = $this->counts();

            $response = $this->actingAs($this->admin)->delete(route('admin.events.destroy', $event));

            $response->assertRedirect()->assertSessionHas('error');
            $this->assertStringContainsString('Archive it instead', session('error'), $label);
            $this->assertNotNull(Event::find($event->id), "$label: event must survive");
            $this->assertNotNull(EventTicket::find($ticket->id), "$label: ticket type must survive");
            $this->assertSame($before, $this->counts(), "$label: nothing deleted");
        }
    }

    public function test_a_refund_or_issued_ticket_alone_also_blocks_deletion(): void
    {
        [$event, $ticket, $order] = $this->eventWithSale();
        $attempt = $this->attemptFor($order);
        PaymentRefund::create(['internal_reference' => 'REF-T', 'payment_attempt_id' => $attempt->id, 'order_id' => $order->id, 'provider' => 'fake', 'mode' => 'manual', 'type' => 'partial', 'status' => 'requested', 'currency' => 'EGP', 'amount_minor' => 1000, 'reason' => 'test', 'idempotency_key' => 'k', 'requested_by' => $this->admin->id, 'meta' => ['timeline' => []]]);

        $deps = $this->lifecycle()->dependencies($event);
        foreach (['order items (sold tickets)', 'orders', 'payment attempts', 'refunds', 'issued tickets', 'tickets with QR / check-in (incl. guest list)'] as $k) {
            $this->assertArrayHasKey($k, $deps['items'], $k);
        }
        $this->expectException(ValidationException::class);
        $this->lifecycle()->delete($event, $this->admin);
    }

    public function test_an_event_without_history_can_be_deleted_permanently_with_its_configuration(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $event->fees()->create(['name' => 'Fee', 'fee_type' => 'percentage', 'value' => 5]);

        $this->actingAs($this->admin)->delete(route('admin.events.destroy', $event))->assertRedirect(route('admin.events.index'))->assertSessionHas('success');

        $this->assertNull(Event::find($event->id));
        $this->assertNull(EventTicket::find($ticket->id));
        $this->assertSame(0, DB::table('event_fees')->count());
    }

    public function test_delete_needs_its_own_permission_and_archive_and_restore_theirs(): void
    {
        [$event] = $this->eventWithSale(false);
        $viewer = tap(User::factory()->create(), fn ($u) => $u->givePermissionTo(['events.view', 'events.update']));

        $this->actingAs($viewer)->delete(route('admin.events.destroy', $event))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.events.archive', $event))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.events.restore', $event))->assertForbidden();
        $this->assertNotNull(Event::find($event->id));
        $this->assertSame('active', $event->fresh()->status);
    }

    /* ------------------------------------------------------------------ archive */

    public function test_archiving_keeps_every_id_and_every_record(): void
    {
        [$event, $ticket, $order] = $this->eventWithSale();
        $this->attemptFor($order);
        $before = $this->counts();
        $ids = [$event->id, $ticket->id, $order->items()->pluck('id')->all(), IssuedTicket::pluck('id')->all()];

        $this->actingAs($this->admin)->post(route('admin.events.archive', $event))->assertRedirect()->assertSessionHas('success');

        $event->refresh();
        $this->assertSame(['archived', 'active'], [$event->status, $event->status_before_archive]);
        $this->assertNotNull($event->archived_at);
        $this->assertSame($this->admin->id, (int) $event->archived_by);
        $this->assertSame($before, $this->counts());
        $this->assertSame($ids, [$event->id, $ticket->fresh()->id, $order->items()->pluck('id')->all(), IssuedTicket::pluck('id')->all()]);
        $this->assertSame('active', $ticket->fresh()->status, 'ticket types are not modified');
        $this->assertEquals(500, $order->items()->first()->ticket_price);
        // idempotent
        $at = $event->archived_at;
        $this->lifecycle()->archive($event, $this->admin);
        $this->assertEquals($at, $event->fresh()->archived_at);
        $this->assertSame('active', $event->fresh()->status_before_archive, 'a second archive never overwrites the remembered status');
    }

    public function test_archived_events_are_off_the_public_site_and_cannot_be_booked(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $this->get(route('front.events.show', $event))->assertOk();
        $this->lifecycle()->archive($event, $this->admin);

        $this->get(route('front.events.show', $event))->assertNotFound();
        $this->get(route('front.events'))->assertOk()->assertDontSee('Archive Night');
        $this->get('/')->assertOk()->assertDontSee('Archive Night');

        $this->expectException(ValidationException::class);
        app(CheckoutSelection::class)->build($event->fresh(), collect([['ticket_id' => $ticket->id, 'qty' => 1]]));
    }

    public function test_placing_an_order_is_refused_once_the_event_is_archived_even_with_a_stale_page(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $units = app(CheckoutSelection::class)->build($event, collect([['ticket_id' => $ticket->id, 'qty' => 1]]))['units'];   // the customer's page, built while on sale
        $this->lifecycle()->archive(Event::find($event->id), $this->admin);
        $attendee = ['name' => 'Fan', 'email' => 'fan@example.test', 'phone' => '+201000000001', 'gender' => 'male'];

        try {
            app(OrderPlacementService::class)->place($this->admin, $event /* stale, still says active */, collect($units), ['first_name' => 'B', 'last_name' => 'W', 'email' => 'b@example.test', 'phone' => '+201000000002'], [$attendee], null, 'fake_card');
            $this->fail('the order must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('closed', $e->errors()['cart'][0]);
        }
        $this->assertSame(0, Order::count());
    }

    public function test_a_hidden_ticket_cannot_be_bought(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $ticket->update(['status' => 'hidden']);

        $this->expectException(ValidationException::class);
        app(CheckoutSelection::class)->build($event, collect([['ticket_id' => $ticket->id, 'qty' => 1]]));
    }

    public function test_no_new_payment_attempt_is_created_for_an_archived_event_but_money_in_flight_still_settles(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $method = $this->makeMethod('fake', 'fake_card');
        $order = $this->makePendingOrder($this->admin, $event, 1, 500);
        $open = $this->attemptFor($order, 'initiated');
        $this->lifecycle()->archive($event, $this->admin);

        try {
            app(PaymentService::class)->start($order->fresh(), $method);
            $this->fail('no new payment session for an archived event');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('archived', $e->getMessage());
        }
        $this->assertSame(1, PaymentAttempt::where('order_id', $order->id)->count(), 'no attempt was created');

        // a payment that was already in flight is settled normally (the customer paid before/while it was archived)
        app(PaymentService::class)->settle($open->fresh(), new \App\Services\Payments\GatewayResult(\App\Services\Payments\GatewayResult::PAID, 'SETTLED-1', 500.0, 'EGP', $open->reference));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, IssuedTicket::where('order_id', $order->id)->count());
        $tickets = IssuedTicket::count();
        app(PaymentService::class)->settle($open->fresh(), new \App\Services\Payments\GatewayResult(\App\Services\Payments\GatewayResult::PAID, 'SETTLED-1', 500.0, 'EGP', $open->reference));
        $this->assertSame($tickets, IssuedTicket::count(), 'replay issues no duplicate tickets');
    }

    public function test_issued_tickets_stay_verifiable_and_checkin_works_for_an_archived_event(): void
    {
        [$event, $ticket, $order] = $this->eventWithSale();
        $number = Ticket::firstOrFail()->ticket_number;
        $this->lifecycle()->archive($event, $this->admin);

        $this->actingAs($this->admin)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $number])->assertOk()->assertJson(['ok' => true]);
        $row = Ticket::where('ticket_number', $number)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.tickets.scanner.status', $row), ['status' => 'checked_in'])->assertRedirect();
        $this->assertSame('checked_in', $row->fresh()->status);
        $this->assertNotNull($row->fresh()->checked_in_at);
        $this->assertSame(2, ScanLog::where('ticket_number', $number)->count());

        // the customer still sees the ticket
        $issued = IssuedTicket::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($order->user)->get(route('front.tickets.show', $issued))->assertOk()->assertSee($issued->ticket_number);
        $this->assertSame(2, IssuedTicket::where('order_id', $order->id)->count(), 'nothing was reissued');
    }

    public function test_a_refund_for_an_old_order_of_an_archived_event_still_works(): void
    {
        [$event, $ticket, $order] = $this->eventWithSale();
        $attempt = $this->attemptFor($order);
        $this->lifecycle()->archive($event, $this->admin);

        $refund = app(RefundService::class)->request($attempt->fresh(), $this->admin, 'partial', '100', 'customer asked for money back', 'archived-1');

        $this->assertSame('requested', $refund->status);
        $this->assertSame($order->id, $refund->order_id);
        $this->assertSame('paid', $order->fresh()->status, 'a refund never touches the order or the tickets');
        $this->assertSame(2, IssuedTicket::where('order_id', $order->id)->count());
    }

    public function test_admin_list_hides_archived_by_default_filters_and_labels_them(): void
    {
        [$live] = $this->eventWithSale(false);
        $old = $this->makeEvent(['name' => 'Old Gala']);
        $this->lifecycle()->archive($old, $this->admin);

        $this->actingAs($this->admin)->get(route('admin.events.index'))->assertOk()->assertSee('Archive Night')->assertDontSee('Old Gala')->assertSee('Archived');
        $only = $this->actingAs($this->admin)->get(route('admin.events.index', ['archived' => 'only']))->assertOk()->assertSee('Old Gala')->assertDontSee('Archive Night');
        $only->assertSee('Restore');
        $this->actingAs($this->admin)->get(route('admin.events.index', ['archived' => 'all']))->assertSee('Old Gala')->assertSee('Archive Night');
        // the delete button appears only where deletion is allowed
        $html = $this->actingAs($this->admin)->get(route('admin.events.index'))->getContent();
        $this->assertStringContainsString('Delete Archive Night permanently', $html);
        [$sold] = [$this->makeEvent(['name' => 'Sold Out Gala'])];
        $this->makePendingOrder($this->admin, $sold, 1);
        $html = $this->actingAs($this->admin)->get(route('admin.events.index'))->getContent();
        $this->assertStringNotContainsString('Delete Sold Out Gala permanently', $html);
        $this->assertStringContainsString('Archive Sold Out Gala', $html);
    }

    public function test_an_archived_event_stays_archived_when_edited_and_shows_a_restore_prompt(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $this->lifecycle()->archive($event, $this->admin);

        $this->actingAs($this->admin)->get(route('admin.events.edit', $event))->assertOk()->assertSee('This event is archived')->assertSee('Restore event');
        $this->actingAs($this->admin)->put(route('admin.events.update', $event), ['name' => 'Archive Night', 'event_date' => $event->event_date->toDateString(), 'event_time' => '21:00', 'location' => 'New place', 'status' => 'active', 'requires_booking_approval' => 0,
            'tickets' => [['id' => $ticket->id, 'name' => 'GA', 'price' => '500', 'status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 6, 'is_couple' => 0]]])->assertSessionHasNoErrors();

        $this->assertSame('archived', $event->fresh()->status, 'a form can never un-archive');
        $this->assertSame('New place', $event->fresh()->location);
    }

    /* ------------------------------------------------------------------ restore */

    public function test_restore_returns_a_ready_event_to_its_previous_status(): void
    {
        [$event] = $this->eventWithSale(false);
        $this->lifecycle()->archive($event, $this->admin);

        $this->actingAs($this->admin)->post(route('admin.events.restore', $event))->assertRedirect()->assertSessionHas('success')->assertSessionMissing('warning');

        $event->refresh();
        $this->assertSame('active', $event->status);
        $this->assertNull($event->archived_at);
        $this->get(route('front.events.show', $event))->assertOk();
    }

    public function test_restore_does_not_republish_an_event_that_is_no_longer_ready(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $this->lifecycle()->archive($event, $this->admin);
        $ticket->update(['status' => 'hidden']);          // nothing left to sell
        $event->forceFill(['event_date' => now()->subDays(3)])->save();

        $this->actingAs($this->admin)->post(route('admin.events.restore', $event))->assertRedirect()->assertSessionHas('warning');

        $this->assertSame('draft', $event->fresh()->status, 'not public until someone fixes it');
        $this->assertStringContainsString('no active ticket type', session('warning'));
        $this->assertStringContainsString('already passed', session('warning'));
        $this->assertSame(0, Event::whereIn('status', ['active', 'sold_out'])->count(), 'nothing public');
    }

    public function test_restore_is_a_noop_for_a_current_event_and_keeps_the_ticket_ids(): void
    {
        [$event, $ticket] = $this->eventWithSale(false);
        $this->lifecycle()->archive($event, $this->admin);
        $this->lifecycle()->restore($event, $this->admin);
        $r = $this->lifecycle()->restore($event->fresh(), $this->admin);

        $this->assertSame('active', $r['restored_to']);
        $this->assertSame($ticket->id, EventTicket::firstOrFail()->id);
        $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('description', 'Event restored')->count());
    }

    public function test_unknown_provider_answers_never_settle_or_collect_silently_and_a_later_paid_answer_does(): void
    {
        [$event] = $this->eventWithSale(false);
        $order = $this->makePendingOrder($this->admin, $event, 1, 500);
        $attempt = $this->attemptFor($order, 'initiated');
        $this->lifecycle()->archive($event, $this->admin);
        $payments = app(PaymentService::class);

        $unknown = $payments->settle($attempt->fresh(), \App\Services\Payments\GatewayResult::unknown('network down'));
        $this->assertSame(PaymentService::OUTCOME_PENDING, $unknown);
        $this->assertSame(['pending_payment', 'initiated', 0], [$order->fresh()->status, $attempt->fresh()->status, IssuedTicket::count()], 'unknown = nothing changes');

        $paid = new \App\Services\Payments\GatewayResult(\App\Services\Payments\GatewayResult::PAID, 'LATE-1', 500.0, 'EGP', $attempt->reference);
        $this->assertSame(PaymentService::OUTCOME_PAID, $payments->settle($attempt->fresh(), $paid), 'a payment started before the archive is honoured by the normal rules');
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, IssuedTicket::where('order_id', $order->id)->count());
    }

    public function test_a_payment_that_arrives_after_the_order_expired_and_the_event_was_archived_is_kept_for_review_not_fulfilled(): void
    {
        [$event] = $this->eventWithSale(false);
        $order = $this->makePendingOrder($this->admin, $event, 1, 500);
        $attempt = $this->attemptFor($order, 'initiated');
        $order->update(['payment_timeout_started_at' => now()->subHours(5), 'payment_timeout_minutes' => 60]);
        app(\App\Services\PendingPaymentExpiryService::class)->expireDueOrders();   // expired by the EXISTING policy
        $this->assertSame('canceled', $order->fresh()->status);
        $this->lifecycle()->archive($event, $this->admin);

        $paid = new \App\Services\Payments\GatewayResult(\App\Services\Payments\GatewayResult::PAID, 'LATE-2', 500.0, 'EGP', $attempt->reference);
        $this->assertSame(PaymentService::OUTCOME_REVIEW_REQUIRED, app(PaymentService::class)->settle($attempt->fresh(), $paid));

        $this->assertSame('canceled', $order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
        $this->assertSame('paid', $attempt->fresh()->status, 'the money was taken: the record is kept');
        $this->assertSame('event_not_on_sale', $attempt->fresh()->meta['needs_review']);
        $this->artisan('payments:review-queue')->expectsOutputToContain($order->order_number)->assertExitCode(0);
    }

    public function test_pending_orders_of_an_archived_event_are_left_to_the_normal_expiry_policy(): void
    {
        [$event] = $this->eventWithSale(false);
        $order = $this->makePendingOrder($this->admin, $event, 1, 500);

        $this->lifecycle()->archive($event, $this->admin);

        $this->assertSame('pending_payment', $order->fresh()->status, 'archiving never cancels pending orders');
    }
}
