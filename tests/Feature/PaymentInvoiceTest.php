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

    /** A real mail transport that always fails, like an SMTP outage: the order must survive it. */
    private function breakMail(): void
    {
        $this->resetMail();
        Mail::extend('boom', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \RuntimeException('smtp down');
            }

            public function __toString(): string
            {
                return 'boom';
            }
        });
        config(['mail.mailers.boom' => ['transport' => 'boom'], 'mail.default' => 'boom']);
    }

    private function fixMail(): void
    {
        $this->resetMail();
        config(['mail.default' => 'array']);
        Mail::fake();
    }

    private function resetMail(): void
    {
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstance('mail.manager');
        Mail::clearResolvedInstance('mailer');
        Mail::clearResolvedInstance('mail');
    }

    private function refund(Order $order, int $minor, string $status = PaymentRefund::SUCCEEDED): PaymentRefund
    {
        return PaymentRefund::create([
            'internal_reference' => 'REF-'.uniqid(), 'order_id' => $order->id, 'provider' => 'fake', 'mode' => 'api', 'type' => 'partial',
            'status' => $status, 'currency' => 'EGP', 'amount_minor' => $minor, 'captured_minor' => (int) round($order->total_amount * 100),
            'idempotency_key' => uniqid(), 'reason' => 'test', 'completed_at' => now(),
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
        $order = $this->order(User::factory()->create(), ['subtotal_amount' => 0, 'total_amount' => 200, 'paid_at' => null]);
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
        Mail::fake();   // marking the order paid already issued tickets and sent the automatic invoice mail

        $this->actingAs($admin)->get(route('admin.orders.invoice', ['order' => $order, 'inline' => 1]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->post(route('admin.orders.invoice.resend', $order))->assertForbidden();
        Mail::assertNotSent(OrderInvoicePaidMail::class);

        $admin->givePermissionTo('orders.update');
        $this->actingAs($admin)->post(route('admin.orders.invoice.resend', $order))->assertSessionHas('success');
        Mail::assertSent(OrderInvoicePaidMail::class, 1);
    }

    public function test_mail_failure_keeps_payment_and_tickets_and_the_retry_job_delivers_the_invoice_once(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, $this->event, 2, 100);

        $this->breakMail();
        $order->forceFill(['status' => 'paid', 'paid_at' => now()->subMinutes(10)])->save();   // observer issues tickets; every mail fails

        $order = $order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(2, $order->issuedTickets()->count());
        $this->assertNotNull($order->tickets_generated_at);
        $this->assertFalse(app(TicketIssuanceService::class)->isComplete($order));

        $this->fixMail();   // the mail server is back
        $this->artisan('orders:retry-ticket-issuance')->assertSuccessful();
        Mail::assertSent(OrderInvoicePaidMail::class, 1);

        $this->artisan('orders:retry-ticket-issuance')->assertSuccessful();
        Mail::assertSent(OrderInvoicePaidMail::class, 1);   // never a duplicate
        $this->assertSame(2, $order->fresh()->issuedTickets()->count());
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_resend_after_a_failed_automatic_send_counts_as_delivered_so_no_duplicate_follows(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, $this->event, 1, 100);
        $this->breakMail();
        $order->forceFill(['status' => 'paid', 'paid_at' => now()->subMinutes(10)])->save();

        $this->fixMail();
        Permission::findOrCreate('orders.update', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo('orders.update');
        $this->actingAs($admin)->post(route('admin.orders.invoice.resend', $order))->assertSessionHas('success');
        Mail::assertSent(OrderInvoicePaidMail::class, 1);

        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());   // the retry job's path
        Mail::assertSent(OrderInvoicePaidMail::class, 1);
    }

    public function test_arabic_text_is_joined_and_in_visual_order_for_the_pdf(): void
    {
        $this->assertSame('Sara Ahmed', \App\Support\ArabicPdfText::shape('Sara Ahmed'));
        $this->assertSame(mb_chr(0xFEAA).mb_chr(0xFEF4).mb_chr(0xFECC).mb_chr(0xFEB3), \App\Support\ArabicPdfText::shape('سعيد'));
        $this->assertSame(mb_chr(0xFEFB), \App\Support\ArabicPdfText::shape('لا'));
    }

    public function test_pages_show_invoice_actions_only_for_paid_orders(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $paid = $this->order($owner);
        $pending = $this->makePendingOrder($owner, $this->event);

        $this->actingAs($owner)->get(route('front.account.orders.show', $paid->order_number))->assertOk()
            ->assertSee('Download invoice')->assertSee(route('front.account.orders.invoice', $paid->order_number), false);
        $this->actingAs($owner)->get(route('front.account.orders.show', $pending->order_number))->assertOk()->assertDontSee('Download invoice');
        $this->actingAs($owner)->get(route('front.account.orders'))->assertOk()
            ->assertSee(route('front.account.orders.invoice', $paid->order_number), false)
            ->assertDontSee(route('front.account.orders.invoice', $pending->order_number), false);

        $admin = User::factory()->create();
        foreach (['orders.view', 'orders.update'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $admin->givePermissionTo($perm);
        }
        $this->actingAs($admin)->get(route('admin.orders.show', $paid))->assertOk()
            ->assertSee('Resend Invoice')->assertSee('Download Invoice')->assertSee('View Invoice');
        $this->actingAs($admin)->get(route('admin.orders.show', $pending))->assertOk()->assertDontSee('Resend Invoice');

        $this->refund($paid, 5000);
        $paid->forceFill(['status' => 'partially_refunded'])->save();
        $this->actingAs($owner)->get(route('front.account.orders.show', $paid->order_number))->assertOk()
            ->assertSee('Download invoice')->assertSee('Partially refunded');
        $this->actingAs($admin)->get(route('admin.orders.show', $paid))->assertOk()->assertSee('Partially');
    }
}
