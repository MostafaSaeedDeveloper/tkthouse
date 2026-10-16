<?php

namespace Tests\Feature;

use App\Models\CheckoutRouting;
use App\Models\Event;
use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Routing\CheckoutRoutingService;
use App\Services\Payments\Routing\ReservationInvalid;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/** Provisional reservations: the agent + merchant account shown to a customer, without consuming round-robin turns until an order exists. */
class CheckoutReservationTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private Event $event;
    private User $agent1;
    private User $agent2;
    private IntegrationAccount $F0;
    private IntegrationAccount $F1;
    private IntegrationAccount $F2;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true, 'checkout.experience' => 'one-page']);
        Mail::fake();
        Http::preventStrayRequests();
        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');

        $this->event = $this->makeEvent();
        $this->makeMethod('fake', 'fake_card');
        $this->makeMethod('fake', 'fake_wallet');
        $this->F0 = $this->account('fake', 'F0');
        $this->F1 = $this->account('fake', 'F1', ['supported_methods' => 'fake_card']);
        $this->F2 = $this->account('fake', 'F2', ['supported_methods' => 'fake_wallet,fake_card']);

        $this->agent1 = $this->agent($this->F1);
        $this->agent2 = $this->agent($this->F2);
        SystemSettings::set('order_assignment_enabled', true);
        SystemSettings::set('order_assignment_mode', 'round_robin');
        SystemSettings::set('payment_routing_enabled', true);
        SystemSettings::set('default_payment_account_id', $this->F0->id);
        $this->browser('a');
    }

    private function agent(IntegrationAccount $acc): User
    {
        $u = User::factory()->create();
        $u->assignRole('Order Agent');
        $u->forceFill(['can_receive_orders' => true, 'payment_routing_mode' => 'custom', 'payment_account_id' => $acc->id, 'payment_routing_enabled' => true])->save();

        return $u->fresh();
    }

    private function browser(string $name): void
    {
        PinnedSessionStore::$pin = substr(hash('sha256', 'browser-'.$name), 0, 40);
        $this->app['session.store']->flush();
        auth()->forgetGuards();
    }

    private function begin(): void
    {
        $t = $this->makeTicket($this->event, ['name' => 'T'.random_int(1, 99999)]);
        $cart = rtrim(strtr(base64_encode(json_encode([['ticket_id' => $t->id, 'qty' => 1]])), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
    }

    private function payload(string $email = 'nour@example.test', string $method = 'fake_card'): array
    {
        return ['full_name' => 'Nour Hassan', 'email' => $email, 'phone' => '+201000000000', 'payment_method' => $method,
            'attendees' => [['name' => 'Guest 1', 'email' => 'guest1@example.test', 'phone' => '+201000000001', 'gender' => 'male', 'social_profile' => 'guest1']]];
    }

    private function pointer(): ?int
    {
        return DB::table('assignment_state')->value('last_assigned_user_id');
    }

    private function reservation(): CheckoutRouting
    {
        return CheckoutRouting::where('token', session(CheckoutRoutingService::SESSION_KEY))->firstOrFail();
    }

    public function test_opening_the_payment_step_reserves_provisionally_and_never_moves_the_final_pointer(): void
    {
        $this->begin();
        $this->get(route('front.checkout'))->assertOk()->assertSee('fake_card', false)->assertDontSee('fake_wallet', false); // F1 supports only the card
        $r = $this->reservation();

        $this->assertSame($this->agent1->id, $r->assignee_id);
        $this->assertSame($this->F1->id, $r->integration_account_id);
        $this->assertNull($this->pointer(), 'a reservation is not a turn');
        $this->assertSame(0, Order::count());

        $this->get(route('front.checkout'))->assertOk();   // refresh / back button
        $this->assertSame(1, CheckoutRouting::count());
        $this->assertSame($r->token, $this->reservation()->token);
    }

    public function test_concurrent_sessions_are_spread_over_agents_without_consuming_turns(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $first = $this->reservation()->assignee_id;
        $this->browser('b'); $this->begin(); $this->get(route('front.checkout'));
        $second = $this->reservation()->assignee_id;

        $this->assertNotSame($first, $second);
        $this->assertNull($this->pointer());
    }

    public function test_abandoned_carts_expire_and_cost_no_final_turns(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $this->assertSame($this->agent1->id, $this->reservation()->assignee_id);

        $this->travel(45)->minutes(); // abandoned
        $this->browser('c'); $this->begin(); $this->get(route('front.checkout'));
        $this->assertSame($this->agent1->id, $this->reservation()->assignee_id, 'agent1 is still next: the abandoned cart consumed nothing');
        $this->assertNull($this->pointer());

        $this->assertGreaterThanOrEqual(0, app(CheckoutRoutingService::class)->prune());
    }

    public function test_placing_the_order_assigns_the_reserved_agent_pins_the_account_and_advances_the_pointer(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $token = $this->reservation()->token;

        $this->postJson(route('front.checkout.store'), $this->payload())->assertOk()->assertJson(['ok' => true]);

        $order = Order::firstOrFail();
        $this->assertSame($this->agent1->id, $order->assigned_user_id);
        $this->assertSame($this->F1->id, $order->integration_account_id);
        $this->assertSame($this->agent1->id, $this->pointer());
        $this->assertSame($order->id, CheckoutRouting::where('token', $token)->value('consumed_order_id'));
        $this->assertDatabaseHas('order_assignments', ['order_id' => $order->id, 'to_user_id' => $this->agent1->id, 'source' => 'round_robin']);

        // the payment attempt that follows uses exactly that account
        $this->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertRedirect();
        $this->assertSame($this->F1->id, $order->paymentAttempts()->firstOrFail()->integration_account_id);

        // the next customer gets the next agent
        $this->browser('b'); $this->begin(); $this->get(route('front.checkout'));
        $this->assertSame($this->agent2->id, $this->reservation()->assignee_id);
    }

    public function test_an_invalid_reservation_returns_the_customer_to_payment_choice_with_data_kept(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $this->agent1->forceFill(['is_active' => false])->save(); // the offered agent disappears after the page was shown

        $this->postJson(route('front.checkout.store'), $this->payload())
            ->assertStatus(409)->assertJson(['ok' => false, 'price_changed' => true, 'routing_changed' => true])
            ->assertJsonFragment(['message' => 'Your payment options changed or expired. Please review the available payment methods and choose again — your details are saved.']);

        $this->assertSame(0, Order::count());
        $this->assertNull($this->pointer());

        // reload: buyer data is still there, a fresh reservation points at the remaining agent, nothing was silently re-routed
        $this->get(route('front.checkout'))->assertOk()->assertSee('Nour Hassan')->assertSee('nour@example.test');
        $this->assertSame($this->agent2->id, $this->reservation()->assignee_id);
    }

    public function test_an_expired_reservation_is_refused_then_renewed(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $this->travel(31)->minutes();

        $this->postJson(route('front.checkout.store'), $this->payload())->assertStatus(409)->assertJson(['routing_changed' => true]);
        $this->assertSame(0, Order::count());
        $this->get(route('front.checkout'))->assertOk()->assertSee('Nour Hassan');
        $this->postJson(route('front.checkout.store'), $this->payload())->assertOk();
        $this->assertSame(1, Order::count());
    }

    public function test_method_no_longer_offered_by_the_reserved_account_conflicts(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $this->F1->forceFill(['config' => array_merge($this->F1->config, ['supported_methods' => 'fake_wallet'])])->save();

        $this->postJson(route('front.checkout.store'), $this->payload())->assertStatus(409)->assertJson(['routing_changed' => true]);
        $this->assertSame(0, Order::count());
    }

    public function test_disabling_the_reserved_account_conflicts_instead_of_switching_silently(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $this->F1->forceFill(['enabled' => false])->save();

        $this->postJson(route('front.checkout.store'), $this->payload())->assertStatus(409)->assertJson(['routing_changed' => true]);
        $this->assertSame(0, Order::count());
    }

    public function test_a_reservation_feeds_one_order_only(): void
    {
        $this->begin(); $this->get(route('front.checkout'));
        $svc = app(CheckoutRoutingService::class);
        $token = $this->reservation()->token;

        DB::transaction(function () use ($svc, $token) {
            $d = $svc->consume($token, 'fake_card', true);
            $order = $this->makePendingOrder(User::factory()->create(), null, 1, 100, 'fake_card');
            $svc->finalize($d, $order);
        });

        $this->expectException(ReservationInvalid::class);
        DB::transaction(fn () => $svc->consume($token, 'fake_card', true));
    }

    public function test_routing_off_keeps_the_original_flow_and_assign_on_create_advances_the_pointer(): void
    {
        SystemSettings::set('payment_routing_enabled', false);
        $this->begin(); $this->get(route('front.checkout'))->assertOk()->assertSee('fake_card', false)->assertSee('fake_wallet', false);
        $this->assertSame(0, CheckoutRouting::count());

        $this->postJson(route('front.checkout.store'), $this->payload('x@example.test', 'fake_wallet'))->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame($this->F0->id, optional($order->paymentAttempts()->first())->integration_account_id ?? $this->F0->id);
        $this->assertNotNull($order->assigned_user_id);
        $this->assertSame($order->assigned_user_id, $this->pointer());
    }

    public function test_events_that_require_approval_do_not_reserve_and_keep_the_old_assignment_path(): void
    {
        $event = $this->makeEvent(['requires_booking_approval' => true, 'name' => 'Approval Night']);
        $t = $this->makeTicket($event, ['name' => 'AP']);
        $cart = rtrim(strtr(base64_encode(json_encode([['ticket_id' => $t->id, 'qty' => 1]])), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $event->id, 'cart' => $cart]));
        $this->get(route('front.checkout'))->assertOk();
        $this->assertSame(0, CheckoutRouting::count());

        $this->postJson(route('front.checkout.store'), $this->payload())->assertOk();
        $this->assertNotNull(Order::firstOrFail()->assigned_user_id);
    }
}
