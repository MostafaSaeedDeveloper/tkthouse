<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Gate check-in: one atomic, authorised, server-side decision. Fixtures only — no real tickets, no notifications. */
class ScannerCheckInTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $fracture;
    private Event $other;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fracture = $this->makeEvent(['name' => 'Fracture Halloween']);
        $this->other = $this->makeEvent(['name' => 'Fracture Halloween - After Party']); // a title that merely starts with the same words
    }

    private function scannerFor(?Event $event = null): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('scanner');
        if ($event) {
            $u->managedEvents()->attach($event->id);
        }

        return $u->fresh();
    }

    /** @return array{0:Order,1:\Illuminate\Support\Collection<int,Ticket>} a really issued, paid order (tickets carry no event_id, exactly like production issuance) */
    private function paid(Event $event, int $n = 2, string $status = 'paid'): array
    {
        $order = $this->makePendingOrder(User::factory()->create(), $event, $n);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());
        if ($status !== 'paid') {
            $order->forceFill(['status' => $status])->saveQuietly();
        }

        return [$order->fresh(), Ticket::where('order_id', $order->id)->orderBy('id')->get()];
    }

    private function in(User $u, string $code, ?int $eventId = null)
    {
        return $this->actingAs($u)->postJson(route('admin.tickets.scanner.checkin'), ['code' => $code, 'event_id' => $eventId]);
    }

    public function test_valid_paid_ticket_checks_in_once_and_is_logged_as_the_original_scan(): void
    {
        [, $tickets] = $this->paid($this->fracture);
        $t = $tickets[0];
        $this->assertNull($t->event_id, 'issued tickets carry no event_id; authorisation must use the order item');
        $scanner = $this->scannerFor($this->fracture);

        $this->in($scanner, $t->ticket_number, $this->fracture->id)->assertOk()->assertJson(['ok' => true, 'result' => 'approved', 'code' => 'approved']);
        $t->refresh();
        $this->assertSame('checked_in', $t->status);
        $this->assertNotNull($t->checked_in_at);
        $this->assertSame(1, ScanLog::where('ticket_id', $t->id)->where('action', 'status_update')->where('new_status', 'checked_in')->count());

        // same ticket again → ALREADY USED, original time + scanner reported, nothing re-written
        $at = $t->checked_in_at;
        $second = $this->in($scanner, $t->ticket_number, $this->fracture->id)->assertStatus(409)->assertJson(['ok' => false, 'result' => 'duplicate', 'code' => 'already_checked_in', 'ticket' => ['checked_in_by_you' => true]]);
        $this->assertSame($at->toIso8601String(), $second->json('ticket.checked_in_at'));
        $this->assertEquals($at, $t->fresh()->checked_in_at);
        $this->assertSame(1, ScanLog::where('ticket_id', $t->id)->where('action', 'status_update')->count(), 'the original check-in is counted once');
        $this->assertSame(1, ScanLog::where('ticket_id', $t->id)->where('action', 'checkin_duplicate')->count(), 'the later attempt is recorded separately');

        // a different scanner sees who admitted it
        $other = $this->scannerFor($this->fracture);
        $this->in($other, $t->ticket_number)->assertStatus(409)->assertJson(['code' => 'already_checked_in', 'ticket' => ['checked_in_by_you' => false, 'checked_in_by' => $scanner->name]]);
    }

    public function test_manual_ticket_number_and_legacy_url_style_payload_both_work(): void
    {
        [, $tickets] = $this->paid($this->fracture, 2);
        $scanner = $this->scannerFor($this->fracture);
        $this->in($scanner, ' '.$tickets[0]->ticket_number.' ')->assertOk()->assertJson(['result' => 'approved']);
        $this->in($scanner, 'https://tkthouse.com/t/'.$tickets[1]->ticket_number)->assertOk()->assertJson(['result' => 'approved']);
    }

    public function test_unknown_or_garbage_codes_are_rejected_and_never_create_anything(): void
    {
        $scanner = $this->scannerFor($this->fracture);
        foreach (['999999999999', 'hello', '0', str_repeat('9', 40)] as $code) {
            $this->in($scanner, $code)->assertStatus(404)->assertJson(['ok' => false, 'result' => 'not_found']);
        }
        $this->assertSame(0, Ticket::count());
        $this->assertSame(4, ScanLog::where('action', 'lookup_failed')->count());
        $this->in($scanner, '')->assertStatus(422);
    }

    public function test_ticket_of_another_event_is_refused_even_when_the_title_looks_similar(): void
    {
        [, $afterParty] = $this->paid($this->other, 1);
        $t = $afterParty[0];
        $fractureScanner = $this->scannerFor($this->fracture);

        $this->in($fractureScanner, $t->ticket_number)->assertStatus(409)->assertJson(['code' => 'wrong_event'])->assertJsonPath('ticket.holder', null);
        $this->assertSame('not_checked_in', $t->fresh()->status);
        $this->assertSame(1, ScanLog::where('ticket_id', $t->id)->where('action', 'checkin_rejected_wrong_event')->count());

        // a device whose operator picked the other event cannot use a scanner account that is not assigned to it
        $this->in($fractureScanner, $t->ticket_number, $this->other->id)->assertStatus(409)->assertJson(['code' => 'event_not_allowed']);
        $this->assertSame('not_checked_in', $t->fresh()->status);

        // an unrestricted account that SELECTS an event is also bound to it
        $admin = $this->createAdmin();
        $this->in($admin, $t->ticket_number, $this->fracture->id)->assertStatus(409)->assertJson(['code' => 'wrong_event']);
        $this->in($admin, $t->ticket_number, $this->other->id)->assertOk();
    }

    public function test_legacy_rows_without_any_event_id_fall_back_to_the_title_but_only_for_assigned_scanners(): void
    {
        $old = Ticket::create(['name' => 'Fracture Halloween - GA', 'price' => 10, 'status' => 'not_checked_in', 'ticket_number' => '7000000001', 'qr_payload' => '7000000001']);
        $this->in($this->scannerFor($this->other), '7000000001')->assertStatus(409)->assertJson(['code' => 'wrong_event']);
        $this->in($this->scannerFor($this->fracture), '7000000001')->assertOk();
    }

    public function test_refunded_canceled_and_unpaid_orders_are_never_admitted(): void
    {
        $scanner = $this->scannerFor($this->fracture);

        [, $r] = $this->paid($this->fracture, 1);
        $r[0]->forceFill(['status' => 'canceled', 'cancellation_source' => Ticket::SOURCE_REFUND, 'cancellation_reason' => 'x', 'canceled_at' => now()])->save();
        $this->in($scanner, $r[0]->ticket_number)->assertStatus(409)->assertJson(['code' => 'refunded']);

        [, $c] = $this->paid($this->fracture, 1);
        $c[0]->forceFill(['status' => 'canceled', 'canceled_at' => now()])->save();
        $this->in($scanner, $c[0]->ticket_number)->assertStatus(409)->assertJson(['code' => 'canceled']);

        foreach (['pending_payment', 'on_hold', 'canceled', 'rejected', 'refunded'] as $st) {
            [, $o] = $this->paid($this->fracture, 1, $st);
            $this->in($scanner, $o[0]->ticket_number)->assertStatus(409)->assertJson(['result' => 'review', 'code' => 'order_not_paid']);
            $this->assertSame('not_checked_in', $o[0]->fresh()->status, "order $st must not admit");
        }

        // a soft-deleted order is not admittable either
        [$del, $d] = $this->paid($this->fracture, 1);
        $del->delete();
        $this->in($scanner, $d[0]->ticket_number)->assertStatus(409)->assertJson(['code' => 'order_not_paid']);

        // partially refunded order: its remaining valid tickets still enter
        [, $p] = $this->paid($this->fracture, 2, 'partially_refunded');
        $this->in($scanner, $p[1]->ticket_number)->assertOk()->assertJson(['result' => 'approved']);
        $this->assertSame(0, ScanLog::where('action', 'status_update')->where('new_status', 'checked_in')->whereIn('ticket_id', [$r[0]->id, $c[0]->id, $d[0]->id])->count());
    }

    public function test_a_rejected_ticket_never_becomes_valid_by_retrying(): void
    {
        [, $r] = $this->paid($this->fracture, 1);
        $r[0]->forceFill(['status' => 'canceled', 'canceled_at' => now()])->save();
        $scanner = $this->scannerFor($this->fracture);
        for ($i = 0; $i < 5; $i++) {
            $this->in($scanner, $r[0]->ticket_number)->assertStatus(409);
        }
        $this->assertSame('canceled', $r[0]->fresh()->status);
        $this->assertNull($r[0]->fresh()->checked_in_at);
    }

    public function test_guest_list_tickets_without_an_order_can_check_in(): void
    {
        $g = Ticket::create(['name' => 'Fracture Halloween - Guest', 'price' => 0, 'status' => 'not_checked_in', 'ticket_number' => '8000000001', 'qr_payload' => '8000000001', 'source' => 'guest_list', 'event_id' => $this->fracture->id]);
        $this->in($this->scannerFor($this->fracture), $g->ticket_number)->assertOk()->assertJson(['result' => 'approved']);
    }

    public function test_responses_expose_no_customer_or_payment_data(): void
    {
        [$order, $t] = $this->paid($this->fracture, 1);
        $t[0]->forceFill(['holder_email' => 'secret.person@example.test', 'holder_phone' => '+201555666777'])->save();
        $scanner = $this->scannerFor($this->fracture);
        foreach ([$this->in($scanner, $t[0]->ticket_number), $this->in($scanner, $t[0]->ticket_number), $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[0]->ticket_number])] as $res) {
            $body = $res->getContent();
            foreach (['secret.person', '+201555666777', $order->order_number, 'fake_card', 'total_amount', 'price'] as $leak) {
                $this->assertStringNotContainsString($leak, $body, "leaked $leak");
            }
        }
        $page = $this->actingAs($scanner)->get(route('admin.tickets.scanner', ['code' => $t[0]->ticket_number]))->assertOk();
        $this->assertStringNotContainsString('secret.person', $page->getContent());
        $this->assertStringNotContainsString($order->order_number, $page->getContent());
    }

    public function test_lookup_never_changes_a_ticket(): void
    {
        [, $t] = $this->paid($this->fracture, 1);
        $scanner = $this->scannerFor($this->fracture);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[0]->ticket_number])->assertOk()->assertJson(['code' => 'ready', 'admit' => false]);
        $this->assertSame('not_checked_in', $t[0]->fresh()->status);
        $this->in($scanner, $t[0]->ticket_number)->assertOk();
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[0]->ticket_number])->assertOk()->assertJson(['code' => 'already_checked_in']);
        $this->assertSame(1, ScanLog::where('ticket_id', $t[0]->id)->where('action', 'status_update')->count());
    }

    public function test_the_old_status_form_goes_through_the_same_atomic_rules(): void
    {
        [, $t] = $this->paid($this->other, 1);
        $fractureScanner = $this->scannerFor($this->fracture);
        $this->actingAs($fractureScanner)->post(route('admin.tickets.scanner.status', $t[0]), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('not_checked_in', $t[0]->fresh()->status);

        [, $ok] = $this->paid($this->fracture, 1);
        $this->actingAs($fractureScanner)->post(route('admin.tickets.scanner.status', $ok[0]), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($fractureScanner)->post(route('admin.tickets.scanner.status', $ok[0]), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, ScanLog::where('ticket_id', $ok[0]->id)->where('action', 'status_update')->count());
    }

    public function test_scanner_only_staff_cannot_cancel_or_reopen_tickets_but_admins_can_and_it_is_audited(): void
    {
        [, $t] = $this->paid($this->fracture, 1);
        $scanner = $this->scannerFor($this->fracture);
        $this->in($scanner, $t[0]->ticket_number)->assertOk();

        foreach (['canceled', 'not_checked_in'] as $st) {
            $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $t[0]), ['status' => $st])->assertForbidden();
        }
        $this->assertSame('checked_in', $t[0]->fresh()->status);

        $this->actingAs($this->createAdmin())->post(route('admin.tickets.scanner.status', $t[0]), ['status' => 'not_checked_in'])->assertRedirect();
        $this->assertSame('not_checked_in', $t[0]->fresh()->status);
        $this->assertNull($t[0]->fresh()->checked_in_at);
        $this->assertSame(1, ScanLog::where('ticket_id', $t[0]->id)->where('previous_status', 'checked_in')->where('new_status', 'not_checked_in')->count());
    }

    public function test_logged_out_devices_and_unauthorised_users_cannot_use_any_scanner_endpoint(): void
    {
        [, $t] = $this->paid($this->fracture, 1);
        $this->postJson(route('admin.tickets.scanner.checkin'), ['code' => $t[0]->ticket_number])->assertUnauthorized();
        $this->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[0]->ticket_number])->assertUnauthorized();
        $this->getJson(route('admin.tickets.scanner.ping'))->assertUnauthorized();
        $this->post(route('admin.tickets.scanner.status', $t[0]), ['status' => 'checked_in'])->assertRedirect();

        $customer = User::factory()->create(); // no role, no permission
        $this->actingAs($customer)->postJson(route('admin.tickets.scanner.checkin'), ['code' => $t[0]->ticket_number])->assertForbidden();
        $this->assertSame('not_checked_in', $t[0]->fresh()->status);
    }

    public function test_csrf_is_enforced_on_the_check_in_endpoint(): void
    {
        [, $t] = $this->paid($this->fracture, 1);
        $scanner = $this->scannerFor($this->fracture);
        // Laravel skips CSRF verification under phpunit; swap in a verifier that does not.
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, fn ($app) => new class($app, $app->make("encrypter")) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests() { return false; }
        });
        $res = $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.checkin'), ['code' => $t[0]->ticket_number]);
        $this->assertSame(419, $res->status());
        $this->assertSame('not_checked_in', $t[0]->fresh()->status);
    }

    public function test_ping_returns_a_fresh_token_for_a_live_session(): void
    {
        $this->actingAs($this->scannerFor($this->fracture))->getJson(route('admin.tickets.scanner.ping'))->assertOk()->assertJsonStructure(['ok', 'csrf', 'time']);
    }

    public function test_scanner_page_renders_event_staff_and_uses_no_external_cdn(): void
    {
        $scanner = $this->scannerFor($this->fracture);
        $html = $this->actingAs($scanner)->get(route('admin.tickets.scanner'))->assertOk()->assertSee('Fracture Halloween')->assertSee($scanner->name)->getContent();
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('cdnjs', $html);
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringContainsString('vendor/html5-qrcode/html5-qrcode-2.3.8.min.js', $html);
        $this->assertFileExists(public_path('vendor/html5-qrcode/html5-qrcode-2.3.8.min.js'));
    }
}
