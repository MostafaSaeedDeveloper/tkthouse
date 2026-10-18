<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Orders\AgentReport;
use App\Services\Payments\PaymentReport;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class PaymentReportTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private function paid(string $provider, string $method, float $amount, $account = null, ?User $soldBy = null): PaymentAttempt
    {
        $o = $this->makePendingOrder(User::factory()->create(), null, 1, $amount, $method);
        $o->forceFill(['status' => 'paid', 'paid_at' => now(), 'sold_by_user_id' => $soldBy?->id, 'assigned_user_id' => $soldBy?->id])->saveQuietly();

        return PaymentAttempt::create(['order_id' => $o->id, 'provider' => $provider, 'method_code' => $method, 'amount' => $amount, 'currency' => 'EGP', 'status' => 'paid',
            'transaction_id' => 'T'.random_int(1, 99999999), 'integration_account_id' => $account?->id, 'paid_at' => now()]);
    }

    private function refund(PaymentAttempt $a, int $minor, string $status, string $type = 'partial'): PaymentRefund
    {
        return PaymentRefund::create(['internal_reference' => 'REF-'.random_int(1, 999999999), 'payment_attempt_id' => $a->id, 'order_id' => $a->order_id, 'integration_account_id' => $a->integration_account_id,
            'provider' => $a->provider, 'mode' => 'manual', 'type' => $type, 'status' => $status, 'amount_minor' => $minor, 'reason' => 'x', 'idempotency_key' => 'k'.random_int(1, 999999999),
            'completed_at' => $status === 'succeeded' ? now() : null, 'meta' => ['timeline' => []]]);
    }

    private function admin(array $perms = []): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (array_merge(['orders.view', 'payments.transactions.view'], $perms) as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo(array_merge(['orders.view', 'payments.transactions.view'], $perms));
    }

    public function test_definitions_collected_refunded_net_and_in_progress_are_kept_apart(): void
    {
        $A = $this->account('paymob', 'A');
        $B = $this->account('fawaterak', 'B');
        $a1 = $this->paid('paymob', 'paymob_card', 300, $A);
        $a2 = $this->paid('fawaterak', 'fawaterak_card', 200, $B);
        PaymentAttempt::create(['order_id' => $a1->order_id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 50, 'currency' => 'EGP', 'status' => 'initiated']);
        PaymentAttempt::create(['order_id' => $a1->order_id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 70, 'currency' => 'EGP', 'status' => 'failed']);

        $this->refund($a1, 10000, 'succeeded');           // 100.00 partial, confirmed
        $this->refund($a2, 20000, 'succeeded', 'full');   // 200.00 full, confirmed
        $this->refund($a1, 5000, 'processing');           // NOT counted
        $this->refund($a1, 1000, 'unknown');              // NOT counted
        $this->refund($a1, 7777, 'failed');               // NOT counted

        $r = app(PaymentReport::class)->build($this->admin(), now()->startOfMonth(), now()->endOfDay(), true);
        $t = $r['totals'];
        $this->assertSame([50000, 2, 30000, 1, 1], [$t['collected'], $t['successful'], $t['refunded'], $t['pending'], $t['failed']]);
        $this->assertSame(50000 - 30000, $t['net']);
        $this->assertSame([2, 1], [$t['refund_count'], $t['partial_refunds']]);
        $this->assertSame([2, 6000], [$t['refunds_in_progress'], $t['refunds_in_progress_amount']]);

        $this->assertSame(10000, $r['by_provider']['paymob']['refunded']);
        $this->assertSame(20000, $r['by_account'][$B->id]['refunded']);
        $this->assertSame(20000, $r['by_account'][$A->id]['net']);
        $this->assertSame(30000, $r['by_method']['paymob_card']['collected']);
    }

    public function test_refunds_follow_the_payments_account_not_the_current_assignee(): void
    {
        $A = $this->account('paymob', 'A');
        $agent1 = User::factory()->create();
        $agent2 = User::factory()->create();
        $a = $this->paid('paymob', 'paymob_card', 100, $A, $agent1);
        $this->refund($a, 2500, 'succeeded');
        $a->order->forceFill(['assigned_user_id' => $agent2->id])->saveQuietly(); // reassigned after the sale

        SystemSettings::set('order_assignment_enabled', true);
        $mgr = $this->admin(['orders.view_all', 'orders.view_agent_stats', 'payments.accounts.view']);
        $rep = app(AgentReport::class)->build($mgr, now()->startOfMonth(), now()->endOfDay());
        $row = $rep['rows']->firstWhere('user_id', $agent1->id);
        $this->assertEqualsWithDelta([100.0, 25.0, 75.0], [$row['collected'], $row['refunded'], $row['net']], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) ($rep['rows']->firstWhere('user_id', $agent2->id)['refunded'] ?? 0), 0.001);
        $this->assertSame(2500, app(PaymentReport::class)->build($mgr, now()->startOfMonth(), now()->endOfDay(), true)['by_account'][$A->id]['refunded']);
    }

    public function test_overview_page_scoping_and_permission_for_account_breakdown(): void
    {
        $A = $this->account('paymob', 'Hidden Merchant');
        $a = $this->paid('paymob', 'paymob_card', 120, $A);
        $this->refund($a, 2000, 'succeeded');

        $plain = $this->admin();
        $this->actingAs($plain)->get(route('admin.payments.index'))->assertOk()->assertSee('Net collected')->assertSee('Provider breakdown')->assertDontSee('Merchant account breakdown')->assertDontSee('Hidden Merchant');
        $priv = $this->admin(['payments.accounts.view']);
        $this->actingAs($priv)->get(route('admin.payments.index'))->assertOk()->assertSee('Merchant account breakdown')->assertSee('Hidden Merchant');

        SystemSettings::set('order_assignment_enabled', true);
        $agent = User::factory()->create();
        $agent->assignRole('Order Agent');
        $agent->givePermissionTo('payments.transactions.view');
        $this->actingAs($agent)->get(route('admin.payments.index'))->assertOk();
        $none = app(PaymentReport::class)->build($agent, now()->startOfMonth(), now()->endOfDay(), false);
        $this->assertSame([0, 0], [$none['totals']['collected'], $none['totals']['refunded']], 'an agent sees only their own orders\' payments');
    }

    public function test_period_filter_uses_the_confirmation_dates(): void
    {
        $a = $this->paid('paymob', 'paymob_card', 100);
        $r = $this->refund($a, 4000, 'succeeded');
        $r->forceFill(['completed_at' => now()->subMonths(2)])->save();

        $rep = app(PaymentReport::class)->build($this->admin(), now()->startOfMonth(), now()->endOfDay(), false);
        $this->assertSame([10000, 0, 10000], [$rep['totals']['collected'], $rep['totals']['refunded'], $rep['totals']['net']]);
    }
}
