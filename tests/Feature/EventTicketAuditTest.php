<?php

namespace Tests\Feature;

use App\Models\EventTicket;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** events:audit-tickets separates a real link (id) from text-only guesses and never writes. */
class EventTicketAuditTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_every_confidence_class_is_reported_and_nothing_is_written(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent(['name' => 'Audit Night']);
        $ga = $this->makeTicket($event, ['name' => 'GA']);
        $twinA = $this->makeTicket($event, ['name' => 'Twin']);
        $twinB = $this->makeTicket($event, ['name' => 'twin']);

        $item = function (array $attrs) use ($user, $event) {
            $order = $this->makePendingOrder($user, $event, 1);
            $order->items()->update($attrs);

            return $order->items()->first();
        };
        $item(['event_ticket_id' => $ga->id, 'ticket_name' => 'Audit Night - GA']);                 // CONFIRMED
        $item(['event_ticket_id' => 9999, 'ticket_name' => 'Audit Night - Gone']);                  // LINKED-TYPE-GONE
        $item(['event_ticket_id' => null, 'ticket_name' => 'Audit Night - GA']);                    // EXACT TEXT
        $item(['event_ticket_id' => null, 'ticket_name' => 'Old Event Name - GA']);                 // PROBABLE (event renamed)
        $item(['event_ticket_id' => null, 'ticket_name' => 'Audit Night - Twin']);                  // exact name once ("Twin"; "twin" differs in case) -> EXACT
        $item(['event_ticket_id' => null, 'ticket_name' => 'Audit Night - Vanished']);              // MISSING
        $item(['event_ticket_id' => null, 'event_id' => null, 'ticket_name' => 'Whatever - GA']);   // UNKNOWN
        $snapshot = [json_encode(DB::table('order_items')->orderBy('id')->get()), EventTicket::count()];

        Artisan::call('events:audit-tickets');
        $out = Artisan::output();

        $row = fn (string $class) => (int) (preg_match('/\|\s*'.preg_quote($class, '/').'\s*\|\s*(\d+)/', $out, $m) ? $m[1] : -1);
        $this->assertSame(1, $row('CONFIRMED'), $out);
        $this->assertSame(1, $row('LINKED-TYPE-GONE'));
        $this->assertSame(2, $row('EXACT TEXT'));
        $this->assertSame(1, $row('PROBABLE'));
        $this->assertSame(1, $row('MISSING'));
        $this->assertSame(1, $row('UNKNOWN'));
        $this->assertStringContainsString('Duplicate ticket-type names inside one event', $out);
        $this->assertStringContainsString('None of them was, or will be, re-linked automatically', $out);
        $this->assertSame($snapshot, [json_encode(DB::table('order_items')->orderBy('id')->get()), EventTicket::count()], 'the report wrote nothing');
        $this->assertSame(1, OrderItem::whereNotNull('event_ticket_id')->where('event_ticket_id', '!=', 9999)->count(), 'text matches were not turned into links');
    }

    public function test_ambiguous_text_is_never_called_exact(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent(['name' => 'Dup Night']);
        $this->makeTicket($event, ['name' => 'VIP']);
        EventTicket::create(['event_id' => $event->id, 'name' => 'VIP', 'price' => 10, 'status' => 'active', 'max_per_order' => 4]); // same name twice
        $order = $this->makePendingOrder($user, $event, 1);
        $order->items()->update(['event_ticket_id' => null, 'ticket_name' => 'Dup Night - VIP']);

        Artisan::call('events:audit-tickets');
        $out = Artisan::output();

        $this->assertMatchesRegularExpression('/\|\s*AMBIGUOUS\s*\|\s*1\s*\|/', $out);
        $this->assertMatchesRegularExpression('/\|\s*EXACT TEXT\s*\|\s*0\s*\|/', $out);
    }
}
