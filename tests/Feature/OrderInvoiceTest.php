<?php

namespace Tests\Feature;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\EventFee;
use App\Models\Order;
use App\Models\User;
use App\Services\Invoices\InvoiceService;
use App\Services\TicketIssuanceService;
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

    public function test_invoice_itemises_the_events_own_fees_and_taxes(): void
    {
        EventFee::create(['event_id' => $this->event->id, 'name' => 'VAT', 'fee_type' => 'percentage', 'value' => 14]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service fee', 'fee_type' => 'fixed', 'value' => 10]);

        $order = $this->paid(User::factory()->create(), 100, 2);   // subtotal 200
        $order->forceFill(['fees_breakdown' => [
            ['name' => 'VAT', 'fee_type' => 'percentage', 'value' => 14, 'amount' => 28],
            ['name' => 'Service fee', 'fee_type' => 'fixed', 'value' => 10, 'amount' => 10],
        ], 'total_amount' => 238])->save();

        $snap = app(InvoiceService::class)->ensure($order->fresh())->invoice_snapshot;

        $this->assertSame(['VAT', 'Service fee'], array_column($snap['fees'], 'name'));
        $this->assertEquals(38.0, $snap['fees_total']);
        $this->assertEquals(238.0, $snap['total']);
        $this->assertEquals(238.0, (float) $order->fresh()->total_amount);   // nothing is recalculated or re-charged

        // Changing the event's fees later never rewrites an issued invoice.
        $this->event->fees()->delete();
        $this->assertSame(['VAT', 'Service fee'], array_column(app(InvoiceService::class)->ensure($order->fresh())->invoice_snapshot['fees'], 'name'));
    }

    public function test_older_orders_without_a_breakdown_are_rebuilt_from_the_event_fees(): void
    {
        EventFee::create(['event_id' => $this->event->id, 'name' => 'VAT', 'fee_type' => 'percentage', 'value' => 14]);
        $order = $this->paid(User::factory()->create(), 100, 2);
        $order->forceFill(['total_amount' => 228])->save();   // 200 + 14%

        $fees = app(InvoiceService::class)->ensure($order->fresh())->invoice_snapshot['fees'];

        $this->assertSame('VAT', $fees[0]['name']);
        $this->assertEquals(28.0, $fees[0]['amount']);
    }

    public function test_when_the_fees_cannot_be_reproduced_a_single_line_is_shown(): void
    {
        $order = $this->paid(User::factory()->create(), 100, 2);
        $order->forceFill(['total_amount' => 215])->save();   // 15 of fees nobody can explain any more

        $fees = app(InvoiceService::class)->ensure($order->fresh())->invoice_snapshot['fees'];

        $this->assertCount(1, $fees);
        $this->assertSame('Fees & taxes', $fees[0]['name']);
        $this->assertEquals(15.0, $fees[0]['amount']);
    }

    public function test_no_fees_means_no_fee_lines(): void
    {
        $this->assertSame([], app(InvoiceService::class)->ensure($this->paid(User::factory()->create()))->invoice_snapshot['fees']);
    }

    public function test_pdf_is_generated(): void
    {
        $pdf = app(InvoiceService::class)->pdf($this->paid(User::factory()->create()));

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_completed_order_email_is_the_invoice_with_both_pdfs(): void
    {
        EventFee::create(['event_id' => $this->event->id, 'name' => 'VAT', 'fee_type' => 'percentage', 'value' => 14]);
        $order = $this->paid(User::factory()->create());
        $order->forceFill(['total_amount' => 228])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order);

        Mail::assertSent(OrderTicketsIssuedMail::class, function (OrderTicketsIssuedMail $mail) {
            $mail->assertSeeInHtml('INV-'.$mail->order->order_number);
            $mail->assertSeeInHtml('VAT (14%)');
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

        $this->actingAs($me)->get(route('front.account.orders', ['status' => 'paid']))->assertOk()->assertSee(route('front.account.orders.invoice', $order->order_number), false);
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
