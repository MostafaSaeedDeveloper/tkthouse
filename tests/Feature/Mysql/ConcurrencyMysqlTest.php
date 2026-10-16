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

    private function paidAttempt(float $amount = 300): array
    {
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
}
