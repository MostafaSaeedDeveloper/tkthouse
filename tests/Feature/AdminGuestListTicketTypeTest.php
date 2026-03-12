<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGuestListTicketTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_list_store_creates_normal_ticket_with_guest_prefixed_type(): void
    {
        $this->withoutMiddleware();

        $admin = User::factory()->create();

        $event = Event::create([
            'name' => 'Demo Event',
            'event_date' => now()->toDateString(),
            'event_time' => '20:00',
            'location' => 'Cairo',
            'description' => 'Event',
            'status' => 'active',
            'requires_booking_approval' => false,
        ]);

        EventTicket::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 100,
            'status' => 'active',
            'max_per_order' => 10,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.guest-lists.store'), [
            'event_id' => $event->id,
            'guest_type' => 'Regular',
            'guests' => [
                ['name' => 'Guest One', 'email' => '', 'phone' => '01000000000'],
            ],
        ]);

        $response->assertRedirect(route('admin.guest-lists.index'));

        $ticket = Ticket::query()->where('ticket_source', 'guest_list')->first();

        $this->assertNotNull($ticket);
        $this->assertSame('Demo Event - Guest Regular', $ticket->name);
        $this->assertSame('Guest Regular', $ticket->guest_category);
        $this->assertSame($ticket->ticket_number, $ticket->qr_payload);
    }
}
