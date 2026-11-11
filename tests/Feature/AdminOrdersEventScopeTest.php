<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The Orders list opens on the Active events; the Event filter steps back to older ones (for assigned agents too). */
class AdminOrdersEventScopeTest extends TestCase
{
    use RefreshDatabase;

    private Event $current;
    private Event $old;
    private Event $archivedOne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $this->current = $this->event('Current Night', 'active');
        $this->old = $this->event('Old Night', Event::ENDED);
        $this->archivedOne = $this->event('Archived Night', Event::ARCHIVED);
    }

    private function event(string $name, string $status): Event
    {
        return Event::create(['name' => $name, 'event_date' => now()->addDays(3)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo', 'description' => '', 'status' => $status]);
    }

    private function staff(array $perms = ['orders.view']): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($perms);

        return $this->actingAs($user)->app->make('auth')->user();
    }

    private function order(string $number, Event $event, string $status = 'paid', ?User $assignee = null): Order
    {
        $customer = Customer::create(['first_name' => 'Cus'.$number, 'last_name' => 'Tester', 'email' => $number.'@example.test', 'phone' => '0100'.random_int(1000000, 9999999)]);
        $order = Order::create(['customer_id' => $customer->id, 'order_number' => $number, 'status' => $status, 'payment_method' => 'cash',
            'subtotal_amount' => 100, 'total_amount' => 100, 'assigned_user_id' => $assignee?->id, 'assigned_at' => $assignee ? now() : null]);
        OrderItem::create(['order_id' => $order->id, 'event_id' => $event->id, 'ticket_name' => $event->name.' - GA', 'ticket_price' => 100,
            'quantity' => 1, 'line_total' => 100, 'holder_name' => 'H', 'holder_email' => 'h@example.test']);

        return $order;
    }

    private function listed(array $query = []): \Illuminate\Support\Collection
    {
        $res = $this->get(route('admin.orders.index', $query))->assertOk();

        return collect($res->viewData('orders')->items())->pluck('order_number');
    }

    public function test_opens_on_the_active_events_and_the_summary_follows(): void
    {
        $this->staff();
        $this->order('1001', $this->current);
        $this->order('1002', $this->old);
        $this->order('1003', $this->archivedOne);

        $res = $this->get(route('admin.orders.index'))->assertOk()->assertSee('data-event-scope', false)->assertSee('Current Night');

        $this->assertSame(['1001'], collect($res->viewData('orders')->items())->pluck('order_number')->all());
        $this->assertSame(1, $res->viewData('summary')['all']);
    }

    public function test_the_filter_steps_back_to_all_events_or_one_older_event(): void
    {
        $this->staff();
        $this->order('1001', $this->current);
        $this->order('1002', $this->old);
        $this->order('1003', $this->archivedOne);

        $this->assertEqualsCanonicalizing(['1001', '1002', '1003'], $this->listed(['event_id' => 'all'])->all());
        $this->assertSame(['1002'], $this->listed(['event_id' => $this->old->id])->all());
        $this->assertSame(['1001'], $this->listed(['event_id' => $this->current->id])->all());

        $this->get(route('admin.orders.index', ['event_id' => 'all']))->assertDontSee('data-event-scope', false);
    }

    public function test_the_event_filter_is_offered_with_active_events_as_the_default_option(): void
    {
        $this->staff();
        $this->order('1002', $this->old);

        $this->get(route('admin.orders.index'))->assertOk()->assertSee('<option value="">Active events</option>', false)->assertSee('<option value="all"', false);
    }

    public function test_looking_an_order_up_is_not_hidden_by_the_default(): void
    {
        $this->staff();
        $this->order('1001', $this->current);
        $this->order('2002', $this->old);

        $this->assertSame(['2002'], $this->listed(['reference' => '2002'])->all());
        $this->assertSame(['2002'], $this->listed(['search' => '2002'])->all());
        $this->assertSame(['2002'], $this->listed(['customer' => 'Cus2002'])->all());
    }

    public function test_with_no_active_event_nothing_is_narrowed(): void
    {
        $this->staff();
        $this->current->update(['status' => Event::ENDED]);
        $this->order('1001', $this->current);
        $this->order('1002', $this->old);

        $this->assertEqualsCanonicalizing(['1001', '1002'], $this->listed()->all());
    }

    public function test_an_assigned_agent_gets_the_same_default_and_can_step_back_only_through_their_own_events(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $agent = $this->staff(['orders.view', 'orders.view_assigned']);
        $other = User::factory()->create();

        $this->order('1001', $this->current, 'paid', $agent);
        $this->order('1002', $this->old, 'paid', $agent);
        $this->order('1003', $this->current, 'paid', $other);       // a colleague's order
        $this->order('1004', $this->archivedOne, 'paid', $other);   // an event this agent has nothing in

        $this->assertSame(['1001'], $this->listed()->all());
        $this->assertEqualsCanonicalizing(['1001', '1002'], $this->listed(['event_id' => 'all'])->all());
        $this->assertSame(['1002'], $this->listed(['event_id' => $this->old->id])->all());
        $this->assertSame([], $this->listed(['event_id' => $this->archivedOne->id])->all());   // not theirs: stays empty

        $names = $this->get(route('admin.orders.index'))->viewData('events')->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Current Night', 'Old Night'], $names);
    }
}
