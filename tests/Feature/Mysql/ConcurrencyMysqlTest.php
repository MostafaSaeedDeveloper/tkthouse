<?php

namespace Tests\Feature\Mysql;

use App\Models\CheckoutRouting;
use App\Models\Customer;
use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsMerchantAccounts;
use Tests\TestCase;

/**
 * Real concurrency (separate PHP processes, committed rows, InnoDB row locks / unique indexes). Runs only against MySQL/MariaDB:
 *   DB_CONNECTION=mysql DB_DATABASE=tkt_test ... php artisan test --filter=ConcurrencyMysqlTest
 */
class ConcurrencyMysqlTest extends TestCase
{
    use BuildsMerchantAccounts, DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Concurrency tests need MySQL/MariaDB (DB_CONNECTION=mysql).');
        }
        parent::setUp();
        config(['payments.fake.enabled' => true]);
        \App\Models\PaymentMethod::updateOrCreate(['code' => 'fake_card'], ['name' => 'Fake', 'provider' => 'fake', 'is_active' => true, 'config' => []]);
        $this->agents = [];
        foreach ([1, 2, 3] as $i) {
            $acc = $this->account('fake', 'F'.$i);
            $u = User::factory()->create();
            $u->assignRole('Order Agent');
            $u->forceFill(['can_receive_orders' => true, 'payment_routing_mode' => 'custom', 'payment_account_id' => $acc->id, 'payment_routing_enabled' => true])->save();
            $this->agents[] = $u->id;
        }
        SystemSettings::set('order_assignment_enabled', true);
        SystemSettings::set('order_assignment_mode', 'default_assignee');
        SystemSettings::set('order_default_assignee_ids', [$this->agents[0]]);
        SystemSettings::set('payment_routing_enabled', true);
        \App\Support\SystemSettings::set('default_payment_account_id', null);
        app(\App\Settings\SettingsService::class)->flush();
        \Illuminate\Support\Facades\Cache::flush();
    }

    private array $agents = [];

    /** @param list<list<string>> $jobs argv lists; all started together */
    private function spawn(array $jobs): array
    {
        $procs = [];
        foreach ($jobs as $i => $argv) {
            $cmd = array_merge([PHP_BINARY, base_path('tests/Support/concurrency_worker.php')], $argv);
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['PAYMENTS_FAKE_ENABLED' => 'true']));
            $procs[$i] = [$p, $pipes];
        }
        $out = [];
        foreach ($procs as $i => [$p, $pipes]) {
            $out[$i] = trim(stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
            proc_close($p);
        }

        return $out;
    }

    public function test_parallel_checkouts_all_go_to_the_default_assignee_and_each_order_is_assigned_once(): void
    {
        $out = $this->spawn(array_map(fn ($i) => ['place', (string) $i], range(1, 12)));

        foreach ($out as $line) {
            $this->assertStringStartsWith('OK:', $line, $line);
        }
        $this->assertSame(12, Order::count());
        $this->assertSame(12, OrderAssignment::count(), 'exactly one assignment row per order');
        $perAgent = Order::query()->selectRaw('assigned_user_id, COUNT(*) c')->groupBy('assigned_user_id')->pluck('c', 'assigned_user_id')->all();
        $this->assertSame([$this->agents[0] => 12], array_map('intval', $perAgent), 'every order goes to the default assignee under concurrency');
        $this->assertSame(12, CheckoutRouting::whereNotNull('consumed_order_id')->count());
        $this->assertSame(12, CheckoutRouting::distinct('consumed_order_id')->count('consumed_order_id'));
    }

    public function test_parallel_reservations_all_name_the_default_assignee(): void
    {
        $out = $this->spawn(array_map(fn ($i) => ['reserve', (string) $i], range(1, 9)));

        foreach ($out as $line) {
            $this->assertMatchesRegularExpression('/^\d+\|\w{40}$/', $line, $line);
        }
        $this->assertSame(0, Order::count());
        $perAgent = collect($out)->map(fn ($l) => (int) explode('|', $l)[0])->countBy()->all();
        $this->assertSame([$this->agents[0] => 9], $perAgent);
    }

    public function test_one_reservation_can_feed_exactly_one_order_when_many_race(): void
    {
        $r = app(\App\Services\Payments\Routing\CheckoutRoutingService::class)->reserve(null, 'solo');
        $out = $this->spawn(array_map(fn () => ['consume', $r->token], range(1, 8)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count(), implode(' / ', $out));
        $this->assertSame(7, collect($out)->filter(fn ($l) => str_starts_with($l, 'INVALID:'))->count(), implode(' / ', $out));
        $this->assertSame(1, Order::count());
    }

    public function test_one_gateway_transaction_can_bind_to_one_attempt_only(): void
    {
        $c = Customer::create(['first_name' => 'W', 'last_name' => 'W', 'email' => 'x@example.test', 'phone' => '0100']);
        $o = Order::create(['customer_id' => $c->id, 'order_number' => '9999999999', 'status' => 'pending_payment', 'payment_method' => 'paymob_card', 'total_amount' => 100]);

        $out = $this->spawn(array_map(fn () => ['tx', (string) $o->id, 'SAME-TX'], range(1, 6)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => $l === 'OK')->count(), implode(' / ', $out));
        $this->assertSame(5, collect($out)->filter(fn ($l) => $l === 'DUP')->count(), implode(' / ', $out));
    }

    private function paidAttempt(float $amount = 300, bool $approvalPolicy = false): array
    {
        SystemSettings::set('refund_approval_required', $approvalPolicy);
        \Spatie\Permission\Models\Permission::findOrCreate('payments.refunds.create', 'web');
        $staff = User::factory()->create();
        $staff->givePermissionTo('payments.refunds.create');
        $acc = $this->account('fake', 'RF', [], [], ['refund_enabled_at' => now()]);
        $c = Customer::create(['first_name' => 'R', 'last_name' => 'F', 'email' => 'rf@example.test', 'phone' => '0100']);
        $o = Order::create(['customer_id' => $c->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => 'paid', 'payment_method' => 'fake_card', 'total_amount' => $amount, 'integration_account_id' => $acc->id]);
        $a = \App\Models\PaymentAttempt::create(['order_id' => $o->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => $amount, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => 'FT-'.random_int(1, 99999), 'integration_account_id' => $acc->id, 'paid_at' => now()]);

        return [$a, $staff];
    }

    public function test_parallel_refund_requests_can_never_over_refund_or_double_send(): void
    {
        [$a, $staff] = $this->paidAttempt(300);

        $out = $this->spawn(array_map(fn ($i) => ['refund', (string) $a->id, (string) $staff->id, 'full', '-', 'key-'.$i], range(1, 8)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count(), implode(' / ', $out));
        $this->assertSame(1, \App\Models\PaymentRefund::count());
        $this->assertSame(30000, (int) \App\Models\PaymentRefund::sum('amount_minor'));
    }

    public function test_parallel_partial_refunds_hold_at_most_the_captured_amount_one_in_flight_at_a_time(): void
    {
        [$a, $staff] = $this->paidAttempt(300);

        $out = $this->spawn(array_map(fn ($i) => ['refund', (string) $a->id, (string) $staff->id, 'partial', '200.00', 'k-'.$i], range(1, 8)));

        $ok = collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count();
        $this->assertSame(1, $ok, implode(' / ', $out));
        $this->assertLessThanOrEqual(30000, (int) \App\Models\PaymentRefund::whereIn('status', \App\Models\PaymentRefund::HOLDING)->sum('amount_minor'));
    }

    public function test_the_same_form_submitted_many_times_in_parallel_makes_one_refund(): void
    {
        [$a, $staff] = $this->paidAttempt(300);

        $out = $this->spawn(array_map(fn () => ['refund', (string) $a->id, (string) $staff->id, 'partial', '100', 'same-key'], range(1, 8)));

        $ids = collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->map(fn ($l) => substr($l, 3))->unique();
        $this->assertCount(1, $ids, implode(' / ', $out));
        $this->assertSame(1, \App\Models\PaymentRefund::count());
    }

    public function test_parallel_approvals_and_submits_send_exactly_once_and_never_before_a_second_person_approves(): void
    {
        [$a, $staff] = $this->paidAttempt(300, true);
        \Spatie\Permission\Models\Permission::findOrCreate('payments.refunds.approve', 'web');
        $approver = User::factory()->create();
        $approver->givePermissionTo('payments.refunds.approve');

        $refund = app(\App\Services\Payments\Refunds\RefundService::class)->request($a, $staff, 'partial', '100', 'parallel approval probe', 'pa-1');
        $this->assertTrue((bool) $refund->awaiting_approval);

        // 1) reconciliation-style submits racing each other BEFORE approval: nothing may be sent
        $early = $this->spawn(array_map(fn () => ['submit', (string) $refund->id], range(1, 6)));
        $this->assertSame('requested', $refund->fresh()->status, implode(' / ', $early));
        $this->assertCount(0, array_filter((array) $refund->fresh()->meta['timeline'], fn ($t) => ($t['event'] ?? '') === 'accepted'));

        // 2) approvals and submits racing together: one approval wins, exactly one provider send
        $out = $this->spawn(array_merge(
            array_map(fn () => ['approve', (string) $refund->id, (string) $approver->id], range(1, 6)),
            array_map(fn () => ['submit', (string) $refund->id], range(1, 4)),
        ));

        $fresh = $refund->fresh();
        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'INVALID:'))->count() >= 5 ? 1 : 0, 'losing approvers are refused: '.implode(' / ', $out));
        $this->assertSame($approver->id, $fresh->approved_by);
        $this->assertContains($fresh->status, ['processing', 'succeeded']);
        $accepted = array_filter((array) $fresh->meta['timeline'], fn ($t) => ($t['event'] ?? '') === 'accepted');
        $this->assertCount(1, $accepted, 'exactly one provider send: '.implode(' / ', $out));
        $this->assertSame(1, \App\Models\PaymentRefund::count());
    }

    public function test_removing_a_ticket_type_while_it_is_being_bought_never_orphans_a_sale(): void
    {
        $user = User::factory()->create();
        $outcomes = ['sold_then_hidden' => 0, 'deleted_before_sale' => 0];

        foreach (range(1, 6) as $trial) {
            $event = \App\Models\Event::create(['name' => 'Race '.$trial, 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false]);
            $ticket = \App\Models\EventTicket::create(['event_id' => $event->id, 'name' => 'GA', 'price' => 100, 'status' => 'active', 'max_per_order' => 4]);

            $out = $this->spawn([['sell', (string) $event->id, (string) $ticket->id, (string) $user->id], ['remove-ticket', (string) $event->id, (string) $ticket->id], ['sell', (string) $event->id, (string) $ticket->id, (string) $user->id]]);

            $sold = \App\Models\OrderItem::where('event_ticket_id', $ticket->id)->count();
            $row = \App\Models\EventTicket::find($ticket->id);
            $this->assertStringStartsWith('OK:', $out[1], implode(' / ', $out));
            if ($sold > 0) {
                $this->assertNotNull($row, 'a ticket type with a sale must still exist: '.implode(' / ', $out));
                $outcomes['sold_then_hidden']++;
            } else {
                $this->assertNull($row, 'no sale happened, so the explicit removal deleted it: '.implode(' / ', $out));
                $outcomes['deleted_before_sale']++;
            }
            // a sale that committed is never left pointing at a missing ticket type
            $this->assertSame(0, \App\Models\OrderItem::where('event_id', $event->id)->whereNotNull('event_ticket_id')->whereNotIn('event_ticket_id', \App\Models\EventTicket::pluck('id'))->count());
            foreach ($out as $k => $line) {
                $this->assertTrue(str_starts_with($line, 'OK:') || str_starts_with($line, 'INVALID:'), 'unexpected worker result: '.$line);
            }
        }
        $this->assertSame(6, array_sum($outcomes));
    }

    private function raceEvent(string $name): array
    {
        $event = \App\Models\Event::create(['name' => $name, 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false]);
        $ticket = \App\Models\EventTicket::create(['event_id' => $event->id, 'name' => 'GA', 'price' => 100, 'status' => 'active', 'max_per_order' => 4]);

        return [$event, $ticket];
    }

    public function test_deleting_an_event_while_orders_are_being_created_never_loses_or_orphans_a_sale(): void
    {
        $user = User::factory()->create();
        $outcome = ['deleted' => 0, 'kept' => 0];

        foreach (range(1, 6) as $trial) {
            [$event, $ticket] = $this->raceEvent('Delete Race '.$trial);
            $sell = ['sell', (string) $event->id, (string) $ticket->id, (string) $user->id];
            $out = $this->spawn([['delete-event', (string) $event->id], $sell, $sell]);

            $okSales = collect([$out[1], $out[2]])->filter(fn ($l) => str_starts_with($l, 'OK:'))->count();
            $items = \App\Models\OrderItem::where('event_ticket_id', $ticket->id)->count();
            foreach ($out as $line) {
                // a checkout that starts after the delete committed simply finds no event (404 for the customer): also a clean refusal
                $this->assertTrue(str_starts_with($line, 'OK:') || str_starts_with($line, 'INVALID:') || str_contains($line, 'ModelNotFoundException'), 'unexpected worker result: '.$line);
            }
            $this->assertSame($okSales, $items, 'every sale that reported success exists, and nothing else: '.implode(' / ', $out));

            if (\App\Models\Event::find($event->id)) {
                $outcome['kept']++;
                $this->assertSame($okSales > 0, str_starts_with($out[0], 'INVALID:'), 'delete is refused exactly when a sale committed: '.implode(' / ', $out));
                $this->assertSame($okSales, \App\Models\OrderItem::where('event_id', $event->id)->count());
            } else {
                $outcome['deleted']++;
                $this->assertSame(0, $okSales, 'the event is gone, so no sale may have succeeded: '.implode(' / ', $out));
                $this->assertSame(0, \App\Models\OrderItem::where('event_ticket_id', $ticket->id)->count());
            }
            $this->assertSame(0, \App\Models\OrderItem::whereNull('event_id')->count(), 'no order item was left without its event');
        }
        $this->assertSame(6, array_sum($outcome));
    }

    public function test_archiving_during_checkout_keeps_every_order_and_closes_the_door_afterwards(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $trial) {
            [$event, $ticket] = $this->raceEvent('Archive Race '.$trial);
            $sell = ['sell', (string) $event->id, (string) $ticket->id, (string) $user->id];
            $out = $this->spawn([$sell, ['archive-event', (string) $event->id], $sell, $sell]);

            $this->assertSame('OK:archived', $out[1], implode(' / ', $out));
            $ok = collect([$out[0], $out[2], $out[3]])->filter(fn ($l) => str_starts_with($l, 'OK:'))->count();
            foreach ([$out[0], $out[2], $out[3]] as $line) {
                $this->assertTrue(str_starts_with($line, 'OK:') || str_starts_with($line, 'INVALID:'), 'unexpected worker result: '.$line);
            }
            $this->assertSame($ok, \App\Models\OrderItem::where('event_id', $event->id)->count(), 'a sale either committed completely or not at all: '.implode(' / ', $out));
            $fresh = \App\Models\Event::findOrFail($event->id);
            $this->assertSame(['archived', 'active'], [$fresh->status, $fresh->status_before_archive]);
            $this->assertSame($ticket->id, \App\Models\EventTicket::where('event_id', $event->id)->value('id'), 'ticket ids unchanged');

            // afterwards nobody can buy
            $late = $this->spawn([$sell]);
            $this->assertStringStartsWith('INVALID:', $late[0], implode(' / ', $late));
            $this->assertSame($ok, \App\Models\OrderItem::where('event_id', $event->id)->count());
        }
    }

    public function test_importing_the_same_provider_transaction_in_parallel_makes_exactly_one_row(): void
    {
        $account = $this->account('fake', 'EXT');
        $out = $this->spawn(array_map(fn () => ['ext-import', (string) $account->id, 'FAKE-RACE-1'], range(1, 8)));

        foreach ($out as $line) {
            $this->assertStringStartsWith('OK:', $line, implode(' / ', $out));
        }
        $this->assertSame(1, \App\Models\ExternalTransaction::where('provider_transaction_id', 'FAKE-RACE-1')->count());
        $this->assertCount(1, collect($out)->unique(), 'every worker saw the same row');
        $this->assertSame(0, Order::count());
    }

    /** Paid order with real ticket rows (committed), a manual (Fawaterak) payment or none at all (legacy). */
    private function orderWithTickets(bool $legacy = false, int $n = 3): array
    {
        SystemSettings::set('refund_approval_required', false);
        foreach (['payments.refunds.create', 'payments.refunds.approve'] as $perm) {
            \Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
        }
        $staff = User::factory()->create();
        $staff->givePermissionTo(['payments.refunds.create', 'payments.refunds.approve']);
        $c = Customer::create(['first_name' => 'R', 'last_name' => 'T', 'email' => 'rt@example.test', 'phone' => '0100']);
        $o = Order::create(['customer_id' => $c->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => 'paid', 'payment_method' => 'fawaterak_card', 'total_amount' => 100 * $n, 'subtotal_amount' => 100 * $n, 'paid_at' => now()]);
        $a = null;
        if (! $legacy) {
            $acc = $this->account('fawaterak', 'FW'.random_int(1, 9999), [], [], ['refund_enabled_at' => now()]);
            $o->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
            $a = \App\Models\PaymentAttempt::create(['order_id' => $o->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => 100 * $n, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => 'FW-'.random_int(1, 99999), 'integration_account_id' => $acc->id, 'paid_at' => now()]);
        }
        $tickets = [];
        for ($i = 1; $i <= $n; $i++) {
            $tickets[] = \App\Models\Ticket::create(['order_id' => $o->id, 'name' => 'Night - GA', 'price' => 100, 'status' => 'not_checked_in', 'ticket_number' => (string) (7000000000 + $o->id * 10 + $i), 'qr_payload' => 'q'.$o->id.$i]);
        }

        return [$o, $a, $staff, $tickets];
    }

    private function consistent(Order $o): void
    {
        $refunds = \App\Models\PaymentRefund::where('order_id', $o->id)->get();
        $ok = $refunds->where('status', 'succeeded');
        $canceled = \App\Models\Ticket::where('order_id', $o->id)->where('status', 'canceled')->count();
        $this->assertSame($ok->isNotEmpty(), $canceled > 0, 'tickets are canceled if and only if a refund succeeded');
        $this->assertSame($ok->isNotEmpty() ? 'refunded' : 'paid', $o->fresh()->status);
    }

    public function test_parallel_confirmations_of_one_refund_succeed_once_and_cancel_each_ticket_once(): void
    {
        [$o, $a, $staff] = $this->orderWithTickets();
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->request($a, $staff, 'full', null, 'concurrency confirm', 'cc-1');

        $out = $this->spawn(array_map(fn ($i) => ['refund-confirm', (string) $r->id, (string) $staff->id, (string) $i], range(1, 8)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => $l === 'OK:succeeded')->count(), implode(' / ', $out));
        $this->assertSame(7, collect($out)->filter(fn ($l) => str_starts_with($l, 'INVALID:'))->count(), implode(' / ', $out));
        $this->assertSame(3, \App\Models\ScanLog::where('action', 'refund_canceled')->count(), 'exactly one cancellation per ticket');
        $this->assertSame(3, \App\Models\PaymentRefundTicket::whereNotNull('canceled_at')->count());
        $this->assertSame(30000, (int) \App\Models\PaymentRefund::where('order_id', $o->id)->where('status', 'succeeded')->sum('amount_minor'));
        $this->consistent($o);
    }

    public function test_a_confirmation_racing_a_failure_never_leaves_a_mixed_state(): void
    {
        [$o, $a, $staff] = $this->orderWithTickets();
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->request($a, $staff, 'full', null, 'concurrency mixed', 'cc-2');

        $jobs = [];
        foreach (range(1, 4) as $i) {
            $jobs[] = ['refund-confirm', (string) $r->id, (string) $staff->id, (string) $i];
            $jobs[] = ['refund-fail', (string) $r->id, (string) $staff->id];
        }
        $out = $this->spawn($jobs);

        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count(), implode(' / ', $out));
        $this->assertContains($r->fresh()->status, ['succeeded', 'failed']);
        $this->consistent($o);
    }

    public function test_parallel_requests_for_the_same_ticket_create_one_refund_even_with_different_keys(): void
    {
        [$o, $a, $staff, $t] = $this->orderWithTickets();
        $out = $this->spawn(array_map(fn ($i) => ['refund-tickets', (string) $a->id, (string) $o->id, (string) $staff->id, (string) $t[0]->id, 'tk-'.$i], range(1, 8)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count(), implode(' / ', $out));
        $this->assertSame(1, \App\Models\PaymentRefund::where('order_id', $o->id)->count());
        $this->assertSame(1, \App\Models\PaymentRefundTicket::where('ticket_id', $t[0]->id)->count());
    }

    public function test_parallel_legacy_requests_for_the_same_tickets_create_one_refund(): void
    {
        [$o, , $staff, $t] = $this->orderWithTickets(true);
        $out = $this->spawn(array_map(fn ($i) => ['refund-tickets', '0', (string) $o->id, (string) $staff->id, $t[0]->id.','.$t[1]->id, 'lg-'.$i], range(1, 8)));

        $this->assertSame(1, collect($out)->filter(fn ($l) => str_starts_with($l, 'OK:'))->count(), implode(' / ', $out));
        $this->assertSame(1, \App\Models\PaymentRefund::where('order_id', $o->id)->whereNull('payment_attempt_id')->count());
    }

    public function test_a_failure_in_the_middle_of_a_confirmation_rolls_back_for_real_on_mysql(): void
    {
        [$o, $a, $staff, $t] = $this->orderWithTickets();
        $svc = app(\App\Services\Payments\Refunds\RefundService::class);
        $r = $svc->request($a, $staff, 'full', null, 'rollback probe', 'rb-1');

        // the SECOND ticket fails to save: the first cancellation, the status change and the order status must all be undone
        $n = 0;
        \App\Models\Ticket::saving(function ($tk) use (&$n) {
            if ($tk->status === 'canceled' && ++$n === 2) {
                throw new \RuntimeException('simulated failure');
            }
        });
        try {
            $svc->confirmSucceeded($r, $staff, 'EXT-1', 'portal screenshot verified', now()->toDateString());
            $this->fail('expected the failure to surface');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('nothing was changed', $e->errors()['refund'][0]);
        }
        \App\Models\Ticket::flushEventListeners();

        $this->assertSame('processing', $r->fresh()->status);
        $this->assertSame('paid', $o->fresh()->status);
        $this->assertSame(0, \App\Models\Ticket::where('order_id', $o->id)->where('status', 'canceled')->count());
        $this->assertSame(0, \App\Models\ScanLog::where('action', 'refund_canceled')->count());
        $this->assertSame(0, \App\Models\PaymentRefundTicket::whereNotNull('canceled_at')->count());
    }

    public function test_payment_callbacks_racing_a_refund_confirmation_can_never_restore_or_duplicate_tickets(): void
    {
        [$o, $a, $staff] = $this->orderWithTickets();
        $o->forceFill(['tickets_generated_at' => now()])->saveQuietly();
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->request($a, $staff, 'full', null, 'callback race', 'cb-1');

        $jobs = [];
        foreach (range(1, 4) as $i) {
            $jobs[] = ['refund-confirm', (string) $r->id, (string) $staff->id, (string) $i];
            $jobs[] = ['issue', (string) $o->id];
            $jobs[] = ['issue', (string) $o->id];
        }
        $out = $this->spawn($jobs);

        $this->assertSame(1, collect($out)->filter(fn ($l) => $l === 'OK:succeeded')->count(), implode(' / ', $out));
        $this->assertSame('refunded', $o->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'canceled'], \App\Models\Ticket::where('order_id', $o->id)->orderBy('id')->pluck('status')->all(), 'no callback restored a refunded ticket');
        $this->assertSame(3, \App\Models\Ticket::where('order_id', $o->id)->count(), 'no ticket was duplicated');
        $this->assertSame(3, \App\Models\ScanLog::where('action', 'refund_canceled')->count());
    }

    public function test_parallel_fawaterak_refund_notifications_and_a_confirmation_record_the_hint_once_and_complete_once(): void
    {
        [$o, $a, $staff] = $this->orderWithTickets();
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->request($a, $staff, 'full', null, 'webhook race', 'wh-1');

        $jobs = [];
        foreach (range(1, 6) as $i) {
            $jobs[] = ['faw-refund-webhook', (string) $a->transaction_id, '300'];
        }
        $jobs[] = ['refund-confirm', (string) $r->id, (string) $staff->id, '1'];
        $jobs[] = ['refund-confirm', (string) $r->id, (string) $staff->id, '2'];
        $out = $this->spawn($jobs);

        foreach (array_slice($out, 0, 6) as $line) {
            $this->assertSame('OK:200', $line, implode(' / ', $out));
        }
        $this->assertSame(1, collect($out)->filter(fn ($l) => $l === 'OK:succeeded')->count(), implode(' / ', $out));
        $r = $r->fresh();
        $this->assertSame('succeeded', $r->status);
        $this->assertLessThanOrEqual(1, collect($r->timeline())->where('event', 'provider_notification_received')->count(), 'the hint is recorded at most once');
        $this->assertSame(1, \App\Models\WebhookEvent::where('event_type', 'refund')->count(), 'one event row for identical deliveries');
        $this->assertSame(3, \App\Models\ScanLog::where('action', 'refund_canceled')->count(), 'tickets canceled exactly once');
        $this->assertSame('refunded', $o->fresh()->status);
        $this->assertSame(30000, (int) \App\Models\PaymentRefund::where('order_id', $o->id)->where('status', 'succeeded')->sum('amount_minor'));
    }
}
