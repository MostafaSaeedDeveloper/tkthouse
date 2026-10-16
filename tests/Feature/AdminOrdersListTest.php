<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminOrdersListTest extends TestCase
{
    use RefreshDatabase;

    private Event $eventA;
    private Event $eventB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $longName = 'The Very Long Winter Open Air Festival 2026 Featuring Many International Headliners & Friends';
        $this->eventA = Event::create(['name' => $longName, 'event_date' => now()->addDays(9)->toDateString(), 'event_time' => '20:00:00', 'location' => 'Cairo', 'description' => '', 'status' => 'active']);
        $this->eventB = Event::create(['name' => 'Roof Night', 'event_date' => now()->addDays(3)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Giza', 'description' => '', 'status' => 'active']);
    }

    private function staff(array $extra = []): User
    {
        $names = ['orders.view', ...$extra];
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($names);

        return $this->actingAs($user)->app->make('auth')->user();
    }

    private function order(string $number, string $status, string $first, Event $event, array $attrs = [], array $tickets = [['GA', 100, 1]]): Order
    {
        $customer = Customer::create(['first_name' => $first, 'last_name' => 'Tester', 'email' => strtolower($first).'@example.test', 'phone' => '0100'.random_int(1000000, 9999999)]);
        $total = collect($tickets)->sum(fn ($t) => $t[1] * $t[2]);
        $order = Order::create(array_merge([
            'customer_id' => $customer->id, 'order_number' => $number, 'status' => $status, 'payment_method' => 'cash',
            'subtotal_amount' => $total, 'total_amount' => $total,
        ], $attrs));
        foreach ($tickets as [$name, $price, $qty]) {
            OrderItem::create(['order_id' => $order->id, 'event_id' => $event->id, 'ticket_name' => $event->name.' - '.$name, 'ticket_price' => $price,
                'quantity' => $qty, 'line_total' => $price * $qty, 'holder_name' => $first.' H', 'holder_email' => 'h@example.test']);
        }

        return $order;
    }

    public function test_list_renders_compact_columns_with_full_event_name_available_and_real_summary(): void
    {
        $this->staff();
        $this->order('900001', 'paid', 'Alice', $this->eventA, [], [['Early Bird', 100, 2], ['VIP Table For Ten With Bottle Service', 500, 1]]);
        $this->order('900002', 'pending_approval', 'Bob', $this->eventB);
        $this->order('900003', 'canceled', 'Cara', $this->eventB);
        $this->order('900004', 'rejected', 'Dina', $this->eventB);

        $response = $this->get(route('admin.orders.index'))->assertOk();
        $response->assertSee('The Very Long Winter Open Air Festival 2026 Featuring Many International Headliners', false)
            ->assertSee('class="a-ord-clamp"', false)
            ->assertSee('2 types')                                  // distinct ticket names, no names dumped into the row
            ->assertDontSee('a-ord-tag')                            // the old coloured ticket badges are gone
            ->assertDontSee('VIP Table For Ten With Bottle Service'); // ticket names stay in Order Details

        $summary = $response->viewData('summary');
        $this->assertSame(4, $summary['all']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['paid']);
        $this->assertSame(2, $summary['canceled']);                 // canceled + rejected
        $this->assertEquals(700.0, $summary['paid_amount']);
    }

    public function test_quick_group_search_customer_event_date_and_combinations(): void
    {
        $this->staff();
        Permission::findOrCreate('showing_orders', 'web');
        auth()->user()->givePermissionTo('showing_orders');

        $old = $this->order('910001', 'paid', 'Alice', $this->eventA);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->order('910002', 'pending_payment', 'Alice', $this->eventB);
        $this->order('910003', 'paid', 'Bob', $this->eventB);

        $numbers = fn (array $q) => $this->get(route('admin.orders.index', $q))->assertOk()->viewData('orders')->pluck('order_number')->sort()->values()->all();

        $this->assertSame(['910001', '910002', '910003'], $numbers([]));
        $this->assertSame(['910002'], $numbers(['group' => 'pending']));
        $this->assertSame(['910001', '910003'], $numbers(['group' => 'paid']));
        $this->assertSame(['910002'], $numbers(['status' => 'pending_payment']));
        $this->assertSame(['910003'], $numbers(['reference' => '910003']));
        $this->assertSame(['910001', '910002'], $numbers(['customer' => 'alice']));
        $this->assertSame(['910001', '910002'], $numbers(['search' => 'Alice Tester']));
        $this->assertSame(['910002', '910003'], $numbers(['event_id' => $this->eventB->id]));
        $this->assertSame(['910002', '910003'], $numbers(['date_from' => now()->subDays(2)->toDateString(), 'date_to' => now()->toDateString()]));
        $this->assertSame(['910001'], $numbers(['date_to' => now()->subDays(30)->toDateString()]));
        // several filters combine, and garbage values are ignored instead of failing
        $this->assertSame(['910003'], $numbers(['group' => 'paid', 'event_id' => $this->eventB->id, 'customer' => 'bob']));
        $this->assertSame(['910001', '910002', '910003'], $numbers(['date_from' => 'nonsense', 'group' => 'unknown']));
    }

    public function test_summary_follows_filters_but_not_the_status_filter_and_respects_hidden_orders(): void
    {
        $user = $this->staff();
        $this->order('920001', 'paid', 'Alice', $this->eventB);
        $this->order('920002', 'pending_payment', 'Alice', $this->eventB);
        $this->order('920003', 'paid', 'Bob', $this->eventB);
        $this->order('920004', 'paid', 'Hidden', $this->eventB, ['exclude_from_statistics' => true]);

        $response = $this->get(route('admin.orders.index', ['customer' => 'alice', 'group' => 'paid']))->assertOk();
        $this->assertCount(1, $response->viewData('orders'));
        $this->assertSame(['all' => 2, 'pending' => 1, 'paid' => 1, 'canceled' => 0, 'paid_amount' => 100.0], $response->viewData('summary'));

        // hidden order is not counted without `showing_orders`, and is with it
        $this->assertSame(3, $this->get(route('admin.orders.index'))->viewData('summary')['all']);
        Permission::findOrCreate('showing_orders', 'web');
        $user->givePermissionTo('showing_orders');
        $this->assertSame(4, $this->get(route('admin.orders.index'))->viewData('summary')['all']);
    }

    public function test_sorting_is_whitelisted_and_pagination_keeps_filters(): void
    {
        $this->staff();
        foreach (range(1, 18) as $i) {
            $this->order('93'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'paid', 'Cust'.$i, $this->eventB, [], [['GA', $i * 10, 1]]);
        }

        $asc = $this->get(route('admin.orders.index', ['sort' => 'total_amount', 'dir' => 'asc']))->viewData('orders');
        $this->assertSame('930001', $asc->first()->order_number);
        $this->assertSame(15, $asc->count());
        $this->assertSame(18, $asc->total());

        $this->get(route('admin.orders.index', ['sort' => 'password; drop table orders', 'dir' => 'sideways']))->assertOk();   // falls back to default order
        $this->assertSame(25, $this->get(route('admin.orders.index', ['per_page' => 25]))->viewData('orders')->perPage());
        $this->assertSame(15, $this->get(route('admin.orders.index', ['per_page' => 9999]))->viewData('orders')->perPage());

        $page2 = $this->get(route('admin.orders.index', ['group' => 'paid', 'page' => 2]))->assertOk();
        $this->assertSame(3, $page2->viewData('orders')->count());
        $page2->assertSee('group=paid', false);
    }

    public function test_row_actions_follow_permissions_and_status(): void
    {
        $this->staff();
        $this->order('940001', 'pending_approval', 'Alice', $this->eventB);

        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee('View order 940001')
            ->assertDontSee('Approve order')->assertDontSee('Delete order')->assertDontSee('Change status')->assertDontSee('More actions');

        $this->staff(['orders.update', 'orders.delete', 'update_status']);
        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee('Approve order 940001')->assertSee('Reject order')->assertSee('Edit order')->assertSee('Change status')->assertSee('Delete order');
    }

    public function test_deleted_orders_use_shared_filters_and_permission(): void
    {
        $this->staff();
        $this->get(route('admin.orders.deleted'))->assertForbidden();

        $this->staff(['orders.deleted.view', 'orders.restore']);
        $a = $this->order('950001', 'paid', 'Alice', $this->eventB);
        $this->order('950002', 'paid', 'Bob', $this->eventB);
        $a->delete();

        $this->get(route('admin.orders.deleted'))->assertOk()->assertSee('950001')->assertDontSee('950002')->assertSee('Restore');
        $this->get(route('admin.orders.deleted', ['customer' => 'bob']))->assertOk()->assertDontSee('950001');
    }

    public function test_order_details_show_separate_sections_and_full_ticket_names(): void
    {
        $this->staff();
        $order = $this->order('960001', 'pending_payment', 'Alice', $this->eventA, ['promo_code' => 'SAVE', 'discount_amount' => 20, 'subtotal_amount' => 700, 'total_amount' => 680],
            [['Early Bird', 100, 2], ['VIP Table For Ten With Bottle Service', 500, 1]]);
        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'card', 'reference' => 'ref-1', 'amount' => 680, 'currency' => 'EGP', 'status' => 'failed', 'failure_reason' => 'Declined']);

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Order &amp; Payment', false)->assertSee('Event Details')->assertSee('Purchased Tickets')->assertSee('Attendee Information')
            ->assertSee('Price Breakdown')->assertSee('Payment Attempts')->assertSee('Internal Notes')->assertSee('Order History')
            ->assertSee('The Very Long Winter Open Air Festival 2026 Featuring Many International Headliners', false)
            ->assertSee('VIP Table For Ten With Bottle Service')
            ->assertSee('Declined')
            ->assertSee('- 20.00 EGP');
    }
}
