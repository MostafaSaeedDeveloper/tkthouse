<?php

namespace Tests\Feature;

use App\Mail\OrderInvoicePaidMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\EventFee;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Invoices\Invoice;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class PaymentInvoiceTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        $this->event = $this->makeEvent(['name' => 'Summer Fest']);
    }

    private function order(User $user, array $o = []): Order
    {
        $order = $this->makePendingOrder($user, $this->event, 2, 100);
        $order->forceFill(array_merge(['status' => 'paid', 'paid_at' => now()], $o))->save();

        return $order->fresh();
    }

    private function refund(Order $order, int $minor, string $status = PaymentRefund::SUCCEEDED): PaymentRefund
    {
        return PaymentRefund::create([
            'internal_reference' => 'REF-'.uniqid(), 'order_id' => $order->id, 'provider' => 'fake', 'mode' => 'api', 'type' => 'partial',
            'status' => $status, 'currency' => 'EGP', 'amount_minor' => $minor, 'captured_minor' => (int) round($order->total_amount * 100),
            'idempotency_key' => uniqid(), 'completed_at' => now(),
        ]);
    }

    public function test_invoice_reflects_the_order_exactly_without_inventing_fees(): void
    {
        $order = $this->order(User::factory()->create(), ['discount_amount' => 20, 'promo_code' => 'SAVE', 'subtotal_amount' => 200, 'total_amount' => 180]);

        $i = Invoice::make($order);

        $this->assertSame(200.0, $i->subtotal);
        $this->assertSame(20.0, $i->discount);
        $this->assertSame([], $i->fees);
        $this->assertSame(180.0, $i->total);
        $this->assertSame('INV-'.$i->orderNumber, $i->number);
        $this->assertSame('GA', $i->lines->first()['name']);
        $this->assertSame(2, $i->lines->first()['qty']);
    }

    public function test_fees_are_itemised_only_when_they_match_the_stored_total(): void
    {
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service Fee', 'fee_type' => 'percentage', 'value' => 10]);
        $matching = $this->order(User::factory()->create(), ['subtotal_amount' => 200, 'total_amount' => 220]);
        $this->assertSame('Service Fee', Invoice::make($matching)->fees[0]['name']);
        $this->assertSame(20.0, Invoice::make($matching)->fees[0]['amount']);

        // Fees were edited after this order: no guessed breakdown, one honest line with the real amount.
        $other = $this->order(User::factory()->create(), ['subtotal_amount' => 200, 'total_amount' => 215]);
        $fees = Invoice::make($other)->fees;
        $this->assertCount(1, $fees);
        $this->assertSame(15.0, $fees[0]['amount']);
        $this->assertSame(215.0, Invoice::make($other)->total);
    }

    public function test_legacy_order_without_subtotal_or_paid_at_still_renders_a_valid_pdf(): void
    {
        $order = $this->order(User::factory()->create(), ['subtotal_amount' => null, 'total_amount' => 200, 'paid_at' => null]);
        $i = Invoice::make($order);

        $this->assertSame(200.0, $i->subtotal);
        $this->assertNull($i->paidAt);
        $this->assertStringStartsWith('%PDF', $i->pdf());
    }

    public function test_no_invoice_before_payment_or_for_unconfirmed_refund_status(): void
    {
        $user = User::factory()->create();
        $pending = $this->makePendingOrder($user, $this->event);
        $this->assertFalse(Invoice::isAvailableFor($pending));

        $handEdited = $this->order($user, ['status' => 'refunded']);   // status typed by hand, no confirmed refund
        $this->assertFalse(Invoice::isAvailableFor($handEdited));
    }

    public function test_refund_keeps_the_invoice_and_shows_the_refunded_amount(): void
    {
        $order = $this->order(User::factory()->create(), ['subtotal_amount' => 200, 'total_amount' => 200]);
        $this->refund($order, 5000);
        $order->forceFill(['status' => 'partially_refunded'])->save();

        $i = Invoice::make($order->fresh());

        $this->assertTrue(Invoice::isAvailableFor($order->fresh()));
        $this->assertSame(200.0, $i->total);          // original payment untouched
        $this->assertSame(50.0, $i->refunded);
        $this->assertSame(150.0, $i->netPaid());
        $this->assertFalse($i->isFullyRefunded());
        $this->assertStringStartsWith('%PDF', $i->pdf());
    }

    public function test_payment_email_is_sent_once_with_pdf_after_payment_and_not_before(): void
    {
        $user = User::factory()->create();
        $pending = $this->makePendingOrder($user, $this->event);
        app(TicketIssuanceService::class)->issueIfPaid($pending->fresh());
        Mail::assertNotSent(OrderInvoicePaidMail::class);

        $order = $this->order($user);
        $svc = app(TicketIssuanceService::class);
        $svc->issueIfPaid($order);
        $svc->issueIfPaid($order->fresh());

        Mail::assertSent(OrderInvoicePaidMail::class, 1);
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Mail::assertSent(OrderInvoicePaidMail::class, function (OrderInvoicePaidMail $mail) use ($order) {
            $mail->assertHasSubject('Your Order Is Confirmed — Payment Invoice Attached | TKT House');
            $this->assertCount(1, $mail->attachments());
            $html = $mail->render();
            $this->assertStringContainsString('#'.preg_replace('/\D/', '', $order->order_number), $html);
            $this->assertStringContainsString('Summer Fest', $html);
            $this->assertStringContainsString('200.00 EGP', $html);
            $this->assertStringContainsString('PAID', $html);

            return true;
        });
    }

    public function test_customer_downloads_only_their_own_invoice(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->order($owner);

        $res = $this->actingAs($owner)->get(route('front.account.orders.invoice', $order->order_number));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $this->actingAs($other)->get(route('front.account.orders.invoice', $order->order_number))->assertNotFound();
        auth()->logout();
        $this->get(route('front.account.orders.invoice', $order->order_number))->assertRedirect();

        $pending = $this->makePendingOrder($owner, $this->event);
        $this->actingAs($owner)->get(route('front.account.orders.invoice', $pending->order_number))->assertNotFound();
    }

    public function test_admin_can_view_download_and_resend_with_permissions(): void
    {
        $order = $this->order(User::factory()->create());
        $admin = User::factory()->create();
        foreach (['orders.view', 'orders.update'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $admin->givePermissionTo('orders.view');

        $this->actingAs($admin)->get(route('admin.orders.invoice', ['order' => $order, 'inline' => 1]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->post(route('admin.orders.invoice.resend', $order))->assertForbidden();
        Mail::assertNotSent(OrderInvoicePaidMail::class);

        $admin->givePermissionTo('orders.update');
        $this->actingAs($admin)->post(route('admin.orders.invoice.resend', $order))->assertSessionHas('success');
        Mail::assertSent(OrderInvoicePaidMail::class, 1);
    }
}
