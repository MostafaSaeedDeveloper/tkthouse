<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Str;

/** Fixtures for payment / checkout tests (test database only, no real gateways). */
trait BuildsOrders
{
    protected function makeEvent(array $o = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Pay Night', 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00',
            'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false,
        ], $o));
    }

    protected function makeTicket(Event $e, array $o = []): EventTicket
    {
        return EventTicket::create(array_merge(['event_id' => $e->id, 'name' => 'GA', 'price' => 100, 'status' => 'active', 'max_per_order' => 6], $o));
    }

    protected function makeMethod(string $provider = 'fake', string $code = null, array $config = []): PaymentMethod
    {
        // Some methods are inserted by migrations, so upsert by code.
        return PaymentMethod::updateOrCreate(
            ['code' => $code ?? $provider.'_card'],
            ['name' => ucfirst($provider), 'provider' => $provider, 'is_active' => true, 'config' => $config],
        );
    }

    /** A pending_payment order with one item per attendee, as checkout creates them. */
    protected function makePendingOrder(User $user, ?Event $event = null, int $people = 2, float $unit = 100, string $method = 'fake_card'): Order
    {
        $event ??= $this->makeEvent();
        $customer = Customer::create(['first_name' => 'Buyer', 'last_name' => 'Test', 'email' => 'buyer'.Str::random(5).'@example.test', 'phone' => '+201000000000']);

        $order = Order::create([
            'customer_id' => $customer->id, 'user_id' => $user->id, 'order_number' => (string) random_int(1000000000, 9999999999),
            'status' => 'pending_payment', 'requires_approval' => false, 'payment_method' => $method,
            'payment_link_token' => Str::random(40), 'payment_timeout_started_at' => now(), 'payment_timeout_minutes' => 120,
            'subtotal_amount' => $unit * $people, 'total_amount' => $unit * $people,
        ]);

        for ($i = 1; $i <= $people; $i++) {
            OrderItem::create([
                'order_id' => $order->id, 'event_id' => $event->id, 'ticket_name' => $event->name.' - GA', 'ticket_price' => $unit,
                'quantity' => 1, 'line_total' => $unit, 'holder_name' => 'Holder '.$i, 'holder_email' => "holder{$i}@example.test",
                'holder_phone' => '+20100000000'.$i, 'holder_gender' => 'male', 'holder_social_profile' => 'h'.$i,
            ]);
        }

        return $order;
    }
}
