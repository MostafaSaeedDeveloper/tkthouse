<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGuestListStatsSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tracks_guest_list_invitations_separately_from_tickets_sold(): void
    {
        $this->withoutMiddleware();

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Guest',
            'last_name' => 'List',
            'email' => 'guest-list@example.com',
            'phone' => '01000000001',
        ]);

        $event = Event::create([
            'name' => 'Guest Stats Event',
            'event_date' => now()->toDateString(),
            'event_time' => '20:00',
            'location' => 'Cairo',
            'description' => 'Test event',
            'status' => 'active',
            'requires_booking_approval' => false,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '600001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'payment_status' => 'paid',
            'total_amount' => 500,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => $event->name.' - VIP',
            'ticket_price' => 250,
            'quantity' => 2,
            'line_total' => 500,
            'holder_name' => 'Buyer',
            'holder_email' => 'buyer@example.com',
            'holder_phone' => '01000000002',
        ]);

        Ticket::create([
            'event_id' => $event->id,
            'name' => $event->name.' - Guest List',
            'price' => 0,
            'status' => 'not_checked_in',
            'ticket_source' => 'guest_list',
            'holder_name' => 'Invited One',
            'ticket_number' => 'GL-1001',
            'qr_payload' => 'GL-1001',
            'issued_at' => now(),
        ]);

        Ticket::create([
            'event_id' => $event->id,
            'name' => $event->name.' - Guest List',
            'price' => 0,
            'status' => 'not_checked_in',
            'ticket_source' => 'guest_list',
            'holder_name' => 'Invited Two',
            'ticket_number' => 'GL-1002',
            'qr_payload' => 'GL-1002',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('ticketsSold', 2)
            ->assertViewHas('guestInvitations', 2);
    }
}
