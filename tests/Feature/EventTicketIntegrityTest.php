<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Checkout\CheckoutSelection;
use App\Services\Checkout\OrderPlacementService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Editing an event never recreates its ticket types: ids, sales history, issued tickets, QR and check-ins survive. */
class EventTicketIntegrityTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (['events.create', 'events.update'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->admin = tap(User::factory()->create(), fn ($u) => $u->givePermissionTo(['events.create', 'events.update']));
    }

    /** The row the form posts for an existing ticket type. */
    private function row(EventTicket $t, array $over = []): array
    {
        return $over + ['id' => $t->id, 'name' => $t->name, 'price' => (string) $t->price, 'status' => $t->status, 'color' => '#0d6efd', 'max_per_order' => $t->max_per_order, 'is_couple' => 0, 'label' => '', 'description' => ''];
    }

    private function newRow(string $name, string $price = '100', array $over = []): array
    {
        return $over + ['name' => $name, 'price' => $price, 'status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 6, 'is_couple' => 0];
    }

    private function save(Event $event, array $tickets, array $removed = [], array $extra = [])
    {
        $payload = array_merge(['name' => $event->name, 'event_date' => $event->event_date->toDateString(), 'event_time' => '21:00', 'location' => 'Cairo', 'status' => 'active', 'requires_booking_approval' => 0], $extra);
        if ($tickets !== null) {
            $payload['tickets'] = $tickets;
        }
        if ($removed) {
            $payload['removed_ticket_ids'] = $removed;
        }

        return $this->actingAs($this->admin)->put(route('admin.events.update', $event), $payload);
    }

    private function eventWith(array ...$specs): array
    {
        $event = $this->makeEvent(['name' => 'Integrity Night']);
        $tickets = [];
        foreach ($specs as $i => $spec) {
            $tickets[] = $this->makeTicket($event, $spec + ['sort_order' => $i]);
        }

        return [$event, ...$tickets];
    }

    private function paidOrder(Event $event, EventTicket $ticket, bool $linked, float $price = 500, int $people = 2): Order
    {
        $order = $this->makePendingOrder($this->admin, $event, $people, $price);
        $order->items()->update(['ticket_name' => $event->name.' - '.$ticket->name] + ($linked ? ['event_ticket_id' => $ticket->id] : []));
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();

        return $order->fresh();
    }

    public function test_editing_the_event_keeps_event_and_ticket_ids(): void
    {
        [$event, $a, $b] = $this->eventWith(['name' => 'GA', 'price' => 100], ['name' => 'VIP', 'price' => 300]);
        $ids = EventTicket::pluck('id')->all();

        $this->save($event, [$this->row($a), $this->row($b)], extra: ['location' => 'New venue'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($ids, EventTicket::orderBy('id')->pluck('id')->all(), 'no ticket type was recreated');
        $this->assertSame('New venue', $event->fresh()->location);
        $this->assertSame($event->id, Event::firstOrFail()->id);
    }

    public function test_rename_and_price_change_update_the_same_record_and_leave_history_untouched(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA', 'price' => 500]);
        $order = $this->paidOrder($event, $a, linked: false, price: 500);
        $before = $order->items()->get(['id', 'ticket_name', 'ticket_price', 'line_total', 'event_id'])->toArray();
        $total = $order->total_amount;

        $this->save($event, [$this->row($a, ['name' => 'General Admission', 'price' => '700'])])->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame(['General Admission', '700.00'], [$a->name, number_format((float) $a->price, 2, '.', '')]);
        $this->assertSame($before, $order->items()->get(['id', 'ticket_name', 'ticket_price', 'line_total', 'event_id'])->toArray(), 'old order items keep their name and the 500 price');
        $this->assertEquals($total, $order->fresh()->total_amount);
        $this->assertEquals(500, $order->items()->first()->ticket_price);
        // the exact-text legacy sales were stamped with the id before the text stopped matching
        $this->assertSame([$a->id], $order->items()->pluck('event_ticket_id')->unique()->all());
    }

    public function test_adding_a_ticket_creates_a_new_id_only_for_the_new_one(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA']);

        $this->save($event, [$this->row($a), $this->newRow('Early Bird', '80')])->assertSessionHasNoErrors();

        $this->assertSame(2, $event->tickets()->count());
        $this->assertSame($a->id, $event->tickets()->first()->id);
        $this->assertGreaterThan($a->id, (int) EventTicket::where('name', 'Early Bird')->value('id'));
    }

    public function test_removing_an_unsold_ticket_deletes_it_only_when_explicitly_marked(): void
    {
        [$event, $a, $b] = $this->eventWith(['name' => 'GA'], ['name' => 'Spare']);

        // absent from the request, but not marked: untouched (partial request / failed page load)
        $this->save($event, [$this->row($a)])->assertSessionHasNoErrors();
        $this->assertNotNull($b->fresh());
        $this->assertSame('active', $b->fresh()->status);

        $this->save($event, [$this->row($a)], [$b->id])->assertSessionHasNoErrors();
        $this->assertNull(EventTicket::find($b->id));
        $this->assertNotNull(EventTicket::find($a->id));
    }

    public function test_a_request_without_any_tickets_never_deletes_them(): void
    {
        [$event, $a, $b] = $this->eventWith(['name' => 'GA'], ['name' => 'VIP']);

        $this->actingAs($this->admin)->put(route('admin.events.update', $event), ['name' => 'Integrity Night', 'event_date' => $event->event_date->toDateString(), 'event_time' => '21:00', 'location' => 'Cairo', 'status' => 'active', 'requires_booking_approval' => 0])->assertSessionHasNoErrors();

        $this->assertSame(2, EventTicket::where('event_id', $event->id)->count());
    }

    public function test_a_sold_ticket_is_hidden_not_deleted_and_the_user_is_told(): void
    {
        [$event, $legacy, $linked] = $this->eventWith(['name' => 'GA'], ['name' => 'VIP']);
        $o1 = $this->paidOrder($event, $legacy, linked: false);
        $o2 = $this->paidOrder($event, $linked, linked: true);

        $response = $this->save($event, [], [$legacy->id, $linked->id])->assertSessionHasNoErrors();

        $response->assertSessionHas('warning');
        $this->assertStringContainsString('GA', session('warning'));
        foreach ([$legacy, $linked] as $t) {
            $this->assertSame('hidden', $t->fresh()->status, 'kept, only hidden');
        }
        $this->assertSame(4, OrderItem::where('event_id', $event->id)->count());
        $this->assertSame(['hidden', 'hidden'], EventTicket::orderBy('id')->pluck('status')->all());
    }

    public function test_reordering_keeps_ids_and_changes_only_the_order(): void
    {
        [$event, $a, $b, $c] = $this->eventWith(['name' => 'A'], ['name' => 'B'], ['name' => 'C']);

        $this->save($event, [$this->row($a, ['position' => 2]), $this->row($b, ['position' => 0]), $this->row($c, ['position' => 1])])->assertSessionHasNoErrors();

        $this->assertSame(['B', 'C', 'A'], $event->fresh()->tickets->pluck('name')->all());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $event->tickets->pluck('id')->all());
    }

    public function test_issued_tickets_qr_and_checkins_survive_an_edit(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA', 'price' => 500]);
        $order = $this->paidOrder($event, $a, linked: true);
        app(TicketIssuanceService::class)->issueIfPaid($order);
        $issued = IssuedTicket::orderBy('id')->get(['id', 'uuid', 'ticket_number', 'ticket_name', 'ticket_price'])->toArray();
        $tickets = Ticket::orderBy('id')->get(['id', 'ticket_number', 'qr_payload', 'name', 'price'])->toArray();
        $this->assertCount(2, $tickets);
        $checkedIn = Ticket::orderBy('id')->first();
        $checkedIn->forceFill(['status' => 'checked_in', 'checked_in_at' => now()])->save();
        $stamp = $checkedIn->fresh()->checked_in_at->toDateTimeString();

        $this->save($event, [$this->row($a, ['name' => 'Renamed', 'price' => '900'])])->assertSessionHasNoErrors();

        $this->assertSame($issued, IssuedTicket::orderBy('id')->get(['id', 'uuid', 'ticket_number', 'ticket_name', 'ticket_price'])->toArray());
        $this->assertSame($tickets, Ticket::orderBy('id')->get(['id', 'ticket_number', 'qr_payload', 'name', 'price'])->toArray(), 'QR payloads and prices are historical');
        $this->assertSame([$stamp, 'checked_in'], [Ticket::orderBy('id')->first()->checked_in_at->toDateTimeString(), Ticket::orderBy('id')->first()->status]);
    }

    public function test_a_ticket_id_of_another_event_is_rejected_and_nothing_changes(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA', 'price' => 100]);
        $other = $this->makeEvent(['name' => 'Other Night']);
        $foreign = $this->makeTicket($other, ['name' => 'Foreign', 'price' => 50]);

        $this->save($event, [$this->row($foreign, ['name' => 'Hijacked', 'price' => '1'])])->assertSessionHasErrors('tickets.0.id');
        $this->assertSame(['Foreign', 50.0], [$foreign->fresh()->name, (float) $foreign->fresh()->price]);

        $this->save($event, [$this->row($a)], [$foreign->id])->assertSessionHasErrors('removed_ticket_ids');
        $this->assertNotNull($foreign->fresh());
    }

    public function test_a_failure_rolls_the_whole_update_back(): void
    {
        [$event, $a, $b] = $this->eventWith(['name' => 'GA', 'price' => 100], ['name' => 'VIP', 'price' => 300]);
        $foreign = $this->makeTicket($this->makeEvent(['name' => 'Elsewhere']), ['name' => 'Foreign']);

        // first row is valid, second is not: the event fields, the first ticket and an explicit removal must all be undone
        $this->save($event, [$this->row($a, ['name' => 'Changed', 'price' => '999']), $this->row($foreign)], [], ['name' => 'Renamed Event', 'location' => 'Elsewhere'])->assertSessionHasErrors();

        $this->assertSame('Integrity Night', $event->fresh()->name);
        $this->assertSame(['GA', '100.00'], [$a->fresh()->name, number_format((float) $a->fresh()->price, 2, '.', '')]);
        $this->assertSame(2, $event->tickets()->count());
    }

    public function test_duplicate_names_and_duplicate_ids_are_rejected(): void
    {
        [$event, $a, $b] = $this->eventWith(['name' => 'GA'], ['name' => 'VIP']);

        $this->save($event, [$this->row($a), $this->row($b, ['name' => 'ga'])])->assertSessionHasErrors('tickets');
        $this->save($event, [$this->row($a), $this->row($a)])->assertSessionHasErrors('tickets.1.id');
        $this->save($event, [$this->row($a), $this->newRow('Ga')])->assertSessionHasErrors('tickets');
        $this->assertSame(['GA', 'VIP'], $event->tickets()->pluck('name')->all());
    }

    public function test_disabling_a_sold_ticket_blocks_new_purchases_but_keeps_history(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA', 'price' => 500]);
        $order = $this->paidOrder($event, $a, linked: true);

        $this->save($event, [$this->row($a, ['status' => 'inactive'])])->assertSessionHasNoErrors();

        $this->expectException(ValidationException::class);
        try {
            app(CheckoutSelection::class)->build($event->fresh(), collect([['ticket_id' => $a->id, 'qty' => 1]]));
        } finally {
            $this->assertSame(2, $order->items()->count());
            $this->assertEquals(500, $order->items()->first()->ticket_price);
        }
    }

    public function test_new_orders_record_the_ticket_type_and_survive_a_later_rename_and_price_change(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA', 'price' => 500, 'max_per_order' => 4]);
        $units = app(CheckoutSelection::class)->build($event, collect([['ticket_id' => $a->id, 'qty' => 2]]))['units'];
        $attendee = ['name' => 'Fan', 'email' => 'fan@example.test', 'phone' => '+201000000001', 'gender' => 'male'];

        $order = app(OrderPlacementService::class)->place($this->admin, $event, collect($units), ['first_name' => 'Buyer', 'last_name' => 'One', 'email' => 'b1@example.test', 'phone' => '+201000000002'], [$attendee, $attendee], null, 'fake_card');

        $this->assertSame([$a->id, $a->id], $order->items()->pluck('event_ticket_id')->all());
        $this->assertSame('Integrity Night - GA', $order->items()->first()->ticket_name);

        $this->save($event, [$this->row($a, ['name' => 'Gold', 'price' => '800'])])->assertSessionHasNoErrors();

        $this->assertSame('Integrity Night - GA', $order->items()->first()->ticket_name, 'the sale keeps the name it was made under');
        $this->assertEquals(500, $order->items()->first()->ticket_price);
        $this->assertSame([$a->id, $a->id], $order->items()->pluck('event_ticket_id')->all());
        // and it still counts as a sale of that ticket type, so removing it hides instead of deleting
        $this->save($event, [], [$a->id]);
        $this->assertSame('hidden', $a->fresh()->status);
    }

    public function test_creating_an_event_ignores_submitted_ids(): void
    {
        $other = $this->makeTicket($this->makeEvent(['name' => 'Other']), ['name' => 'Foreign']);

        $this->actingAs($this->admin)->post(route('admin.events.store'), ['name' => 'Brand New', 'event_date' => now()->addDays(3)->toDateString(), 'event_time' => '20:00', 'location' => 'Cairo', 'status' => 'active', 'requires_booking_approval' => 0,
            'tickets' => [$this->newRow('GA') + ['id' => $other->id]]])->assertSessionHasNoErrors();

        $created = Event::where('name', 'Brand New')->firstOrFail();
        $this->assertSame(1, $created->tickets()->count());
        $this->assertNotSame($other->id, $created->tickets()->first()->id);
        $this->assertSame('Foreign', $other->fresh()->name);
    }

    public function test_the_audit_report_is_read_only_and_flags_orphaned_sales(): void
    {
        [$event, $a] = $this->eventWith(['name' => 'GA']);
        $order = $this->paidOrder($event, $a, linked: false);
        $order->items()->update(['ticket_name' => $event->name.' - Vanished']); // a sale whose ticket type is gone
        $before = [OrderItem::count(), EventTicket::count(), $order->items()->pluck('ticket_name')->all()];

        $this->artisan('events:audit-tickets')->expectsOutputToContain('Read-only audit')->expectsOutputToContain('matches no current ticket type')->assertExitCode(0);

        $this->assertSame($before, [OrderItem::count(), EventTicket::count(), $order->items()->pluck('ticket_name')->all()]);
    }
}
