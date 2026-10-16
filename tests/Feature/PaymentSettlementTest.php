<?php

namespace Tests\Feature;

use App\Mail\HolderTicketsIssuedMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\PendingPaymentExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Payment reliability through the fake gateway: no real gateway, mail or WhatsApp is ever contacted. */
class PaymentSettlementTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->makeMethod('fake', 'fake_card');
        $this->order = $this->makePendingOrder($this->user, null, 2, 150);
    }

    private function openGateway(): PaymentAttempt
    {
        $this->actingAs($this->user)
            ->get(route('front.orders.payment.gateway', ['order' => $this->order, 'token' => $this->order->payment_link_token, 'method' => 'fake_card']))
            ->assertRedirect();

        return $this->order->paymentAttempts()->latest('id')->firstOrFail();
    }

    private function simulate(PaymentAttempt $attempt, string $outcome)
    {
        return $this->actingAs($this->user)->post(route('front.payments.fake.complete', $attempt), ['outcome' => $outcome]);
    }

    public function test_paid_flow_marks_order_paid_and_issues_each_ticket_once(): void
    {
        $attempt = $this->openGateway();
        $this->assertSame('initiated', $attempt->status);

        $this->simulate($attempt, 'paid')->assertRedirect(route('front.checkout.confirmation', $this->order->order_number));

        $order = $this->order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->tickets_generated_at);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame(2, IssuedTicket::where('order_id', $order->id)->count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Mail::assertSent(HolderTicketsIssuedMail::class, 2);
    }

    public function test_duplicate_callbacks_do_not_issue_or_notify_twice(): void
    {
        $attempt = $this->openGateway();

        $this->simulate($attempt, 'paid');
        $this->simulate($attempt, 'paid');
        app(PaymentService::class)->refresh($this->order->fresh());
        app(PaymentService::class)->refresh($this->order->fresh(), 'fake_'.$attempt->id, 'fake');

        $this->assertSame(2, IssuedTicket::where('order_id', $this->order->id)->count());
        $this->assertSame(2, \App\Models\Ticket::where('order_id', $this->order->id)->count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Mail::assertSent(HolderTicketsIssuedMail::class, 2);
        $this->assertSame(1, PaymentAttempt::where('order_id', $this->order->id)->where('status', 'paid')->count());
    }

    public function test_repeated_issuance_calls_are_idempotent(): void
    {
        $attempt = $this->openGateway();
        $this->simulate($attempt, 'paid');

        $service = app(\App\Services\TicketIssuanceService::class);
        $service->issueIfPaid($this->order->fresh());
        $service->issueIfPaid($this->order->fresh());

        $this->assertSame(2, IssuedTicket::where('order_id', $this->order->id)->count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_failed_payment_keeps_order_open_and_can_be_retried_safely(): void
    {
        $first = $this->openGateway();
        $this->simulate($first, 'failed');

        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());

        $second = $this->openGateway();
        $this->assertNotSame($first->id, $second->id);

        $this->simulate($second, 'paid');
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
    }

    public function test_pending_payment_is_not_treated_as_paid(): void
    {
        $attempt = $this->openGateway();
        $this->simulate($attempt, 'pending');

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('initiated', $attempt->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
    }

    public function test_double_click_and_refresh_reuse_one_gateway_session(): void
    {
        $a = $this->openGateway();
        $b = $this->openGateway();
        $c = $this->openGateway();

        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->id, $c->id);
        $this->assertSame(1, PaymentAttempt::where('order_id', $this->order->id)->count());
    }

    public function test_reconcile_settles_a_payment_whose_callback_never_arrived(): void
    {
        $attempt = $this->openGateway();
        // The "gateway" now says paid, but nobody called us back.
        $attempt->forceFill(['meta' => array_merge($attempt->meta, ['fake_state' => 'paid']), 'created_at' => now()->subMinutes(10)])->save();

        $this->artisan('orders:reconcile-payments')->assertExitCode(0);

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());

        $this->artisan('orders:reconcile-payments')->assertExitCode(0);
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_amount_mismatch_never_marks_the_order_paid(): void
    {
        $attempt = $this->openGateway();
        $attempt->update(['meta' => array_merge($attempt->meta, ['fake_state' => 'paid', 'fake_amount' => 1.00])]);

        app(PaymentService::class)->refresh($this->order->fresh());

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame('amount_mismatch', $attempt->fresh()->failure_reason);
        $this->assertSame(0, IssuedTicket::count());
    }

    public function test_payment_after_the_deadline_reinstates_an_expired_order_once(): void
    {
        $this->makeTicket(\App\Models\Event::find($this->order->items()->first()->event_id)); // still on sale
        $attempt = $this->openGateway();
        $this->order->update(['payment_timeout_started_at' => now()->subHours(5), 'payment_timeout_minutes' => 60]);
        app(PendingPaymentExpiryService::class)->expireDueOrders();
        $this->assertSame('canceled', $this->order->fresh()->status);

        $this->simulate($attempt, 'paid');

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
        $this->simulate($attempt, 'paid');
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_payment_for_an_order_canceled_by_staff_is_flagged_not_fulfilled(): void
    {
        $attempt = $this->openGateway();
        $this->order->update(['status' => 'canceled']);

        $this->simulate($attempt, 'paid');

        $this->assertSame('canceled', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseHas('activity_log', ['description' => 'Payment received on a closed order', 'subject_id' => $this->order->id]);
    }

    public function test_a_second_payment_for_an_already_paid_order_is_recorded_as_duplicate(): void
    {
        $first = $this->openGateway();
        $this->simulate($first, 'paid');

        $second = PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 300, 'status' => 'initiated',
            'transaction_id' => 'fake_x', 'meta' => ['fake_state' => 'paid']]);
        app(PaymentService::class)->refresh($this->order->fresh());

        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Duplicate payment received', 'subject_id' => $this->order->id]);
    }

    public function test_customers_can_no_longer_mark_their_own_order_as_paid(): void
    {
        $this->actingAs($this->user)
            ->post('/orders/'.$this->order->id.'/payment/'.$this->order->payment_link_token, ['payment_method' => 'fake_card'])
            ->assertStatus(405);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_gateway_redirect_enforces_ownership_token_and_status(): void
    {
        $other = User::factory()->create();
        $url = route('front.orders.payment.gateway', ['order' => $this->order, 'token' => $this->order->payment_link_token, 'method' => 'fake_card']);

        // Someone else holding the link is asked to prove they own the order's mailbox, never let in.
        $this->actingAs($other)->get($url)->assertStatus(401)->assertSee('Confirm it');
        $this->actingAs($this->user)->get(route('front.orders.payment.gateway', ['order' => $this->order, 'token' => 'wrong', 'method' => 'fake_card']))->assertNotFound();
        $this->order->update(['status' => 'canceled']);
        $this->actingAs($this->user)->get($url)->assertNotFound();
    }

    public function test_fake_gateway_is_unavailable_when_disabled(): void
    {
        config(['payments.fake.enabled' => false]);

        $this->actingAs($this->user)
            ->get(route('front.orders.payment.gateway', ['order' => $this->order, 'token' => $this->order->payment_link_token, 'method' => 'fake_card']))
            ->assertRedirect(route('front.orders.payment', ['order' => $this->order, 'token' => $this->order->payment_link_token]));

        $this->assertSame(0, PaymentAttempt::count());
    }
}
