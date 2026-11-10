<?php

namespace Tests\Feature;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Invoices\InvoiceService;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderInvoiceTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        $this->event = $this->makeEvent();
    }

    private function paid(User $user, float $unit = 100, int $people = 2): Order
    {
        $order = $this->makePendingOrder($user, $this->event, $people, $unit);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        return $order->fresh();
    }

    public function test_tax_is_broken_out_of_the_inclusive_total_and_snapshotted(): void
    {
        SystemSettings::set('invoice_tax_rate', '14');
        $order = $this->paid(User::factory()->create(), 100, 2);   // total 200 incl. 14%

        $issued = app(InvoiceService::class)->ensure($order);

        $this->assertSame('INV-'.$order->order_number, $issued->invoice_number);
        $this->assertEquals(175.44, $issued->invoice_snapshot['net']);
        $this->assertEquals(24.56, $issued->invoice_snapshot['tax_amount']);
        $this->assertEquals(200.0, $issued->invoice_snapshot['total']);
        $this->assertEquals(200.0, (float) $issued->fresh()->total_amount);   // what is charged never changes

        // A later rate change never rewrites an issued invoice.
        SystemSettings::set('invoice_tax_rate', '5');
        $this->assertEquals(14.0, app(InvoiceService::class)->ensure($order->fresh())->invoice_snapshot['tax']['rate']);
    }

    public function test_tax_can_be_switched_off(): void
    {
        SystemSettings::set('invoice_tax_enabled', false);
        $snap = app(InvoiceService::class)->ensure($this->paid(User::factory()->create()))->invoice_snapshot;

        $this->assertFalse($snap['tax']['enabled']);
        $this->assertEquals(0.0, $snap['tax_amount']);
        $this->assertEquals($snap['total'], $snap['net']);
    }

    public function test_pdf_is_generated(): void
    {
        $pdf = app(InvoiceService::class)->pdf($this->paid(User::factory()->create()));

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_completed_order_email_is_the_invoice_with_both_pdfs(): void
    {
        $order = $this->paid(User::factory()->create());
        app(TicketIssuanceService::class)->issueIfPaid($order);

        Mail::assertSent(OrderTicketsIssuedMail::class, function (OrderTicketsIssuedMail $mail) {
            $mail->assertSeeInHtml('INV-'.$mail->order->order_number);
            $mail->assertSeeInHtml('VAT 14%');
            $names = collect($mail->attachments())->map(fn ($a) => $a->as)->all();
            $this->assertContains('invoice-'.$mail->order->order_number.'.pdf', $names);
            $this->assertContains('tickets-'.$mail->order->order_number.'.pdf', $names);

            return true;
        });
    }

    public function test_owner_downloads_the_invoice_from_the_portal(): void
    {
        $me = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->paid($me);

        $this->actingAs($me)->get(route('front.account.orders', ['status' => 'paid']))->assertOk()->assertSee('Invoice (PDF)');
        $this->actingAs($me)->get(route('front.account.orders.show', $order->order_number))->assertOk()->assertSee('Download invoice (PDF)');

        $res = $this->actingAs($me)->get(route('front.account.orders.invoice', $order->order_number));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_invoice_is_private_and_only_for_paid_orders(): void
    {
        $me = User::factory()->create(['email_verified_at' => now()]);
        $theirs = $this->paid(User::factory()->create(['email_verified_at' => now()]));
        $unpaid = $this->makePendingOrder($me, $this->event, 1);

        $this->get(route('front.account.orders.invoice', $theirs->order_number))->assertRedirect();
        $this->actingAs($me)->get(route('front.account.orders.invoice', $theirs->order_number))->assertNotFound();
        $this->actingAs($me)->get(route('front.account.orders.invoice', $unpaid->order_number))->assertNotFound();
        $this->actingAs($me)->get(route('front.account.orders.show', $unpaid->order_number))->assertDontSee('Download invoice');
    }
}
