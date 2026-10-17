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
        SystemSettings::set('order_assignment_mode', 'round_robin');
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

    public function test_parallel_checkouts_get_balanced_agents_and_each_order_is_assigned_once(): void
    {
        $out = $this->spawn(array_map(fn ($i) => ['place', (string) $i], range(1, 12)));

        foreach ($out as $line) {
            $this->assertStringStartsWith('OK:', $line, $line);
        }
        $this->assertSame(12, Order::count());
        $this->assertSame(12, OrderAssignment::count(), 'exactly one assignment row per order');
        $perAgent = Order::query()->selectRaw('assigned_user_id, COUNT(*) c')->groupBy('assigned_user_id')->pluck('c', 'assigned_user_id')->all();
        $this->assertEqualsCanonicalizing([4, 4, 4], array_values($perAgent), 'round robin stays perfectly balanced under concurrency');
        $this->assertSame(12, CheckoutRouting::whereNotNull('consumed_order_id')->count());
        $this->assertSame(12, CheckoutRouting::distinct('consumed_order_id')->count('consumed_order_id'));
    }

    public function test_provisional_reservations_never_move_the_final_pointer_under_parallel_load(): void
    {
        $out = $this->spawn(array_map(fn ($i) => ['reserve', (string) $i], range(1, 9)));

        foreach ($out as $line) {
            $this->assertMatchesRegularExpression('/^\d+\|\w{40}$/', $line, $line);
        }
        $this->assertNull(DB::table('assignment_state')->value('last_assigned_user_id'));
        $this->assertSame(0, Order::count());
        // concurrent sessions were spread over the three agents (held agents are skipped)
        $perAgent = collect($out)->map(fn ($l) => (int) explode('|', $l)[0])->countBy()->all();
        $this->assertCount(3, $perAgent);
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
}
