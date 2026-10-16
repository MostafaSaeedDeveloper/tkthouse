<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminHiddenOrdersAccessTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Order $visible;
    private Order $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $this->customer = Customer::create(['first_name' => 'Hid', 'last_name' => 'Den', 'email' => 'hid@example.com', 'phone' => '0100']);
        $this->visible = $this->order('800001', false);
        $this->hidden = $this->order('800002', true);
    }

    private function order(string $number, bool $excluded): Order
    {
        return Order::create([
            'customer_id' => $this->customer->id, 'order_number' => $number, 'status' => 'pending_payment',
            'payment_method' => 'cash', 'total_amount' => 100, 'exclude_from_statistics' => $excluded,
        ]);
    }

    private function staff(array $permissions, bool $withShowing): User
    {
        foreach ([...$permissions, 'showing_orders'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($withShowing ? [...$permissions, 'showing_orders'] : $permissions);

        return $user;
    }

    private const PERMS = ['orders.view', 'orders.update', 'orders.delete', 'orders.deleted.view', 'orders.restore', 'update_status'];

    public function test_without_showing_orders_hidden_orders_are_not_listed_or_reachable(): void
    {
        $user = $this->staff(self::PERMS, false);
        $this->actingAs($user);

        $this->get(route('admin.orders.index'))->assertOk()->assertSee('800001')->assertDontSee('800002');

        $this->get(route('admin.orders.show', $this->hidden))->assertNotFound();
        $this->get(route('admin.orders.edit', $this->hidden))->assertNotFound();
        $this->put(route('admin.orders.update', $this->hidden), ['status' => 'paid'])->assertNotFound();
        $this->post(route('admin.orders.status.update', $this->hidden), ['status' => 'paid'])->assertNotFound();
        $this->post(route('admin.orders.notes.store', $this->hidden), ['body' => 'x'])->assertNotFound();
        $this->post(route('admin.orders.approve', $this->hidden))->assertNotFound();
        $this->post(route('admin.orders.reject', $this->hidden))->assertNotFound();
        $this->delete(route('admin.orders.destroy', $this->hidden))->assertNotFound();

        $this->assertNotSoftDeleted($this->hidden);
        $this->assertSame('pending_payment', $this->hidden->fresh()->status);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'order_notes']);
    }

    public function test_without_showing_orders_deleted_hidden_orders_cannot_be_listed_or_restored(): void
    {
        $this->hidden->delete();
        $this->visible->delete();
        $this->actingAs($this->staff(self::PERMS, false));

        $this->get(route('admin.orders.deleted'))->assertOk()->assertSee('800001')->assertDontSee('800002');
        $this->post(route('admin.orders.restore', $this->hidden->id))->assertNotFound();
        $this->assertSoftDeleted($this->hidden);

        $this->post(route('admin.orders.restore', $this->visible->id))->assertRedirect(route('admin.orders.deleted'));
        $this->assertNotSoftDeleted($this->visible);
    }

    public function test_visible_orders_stay_accessible_without_showing_orders(): void
    {
        $this->actingAs($this->staff(self::PERMS, false));

        $this->get(route('admin.orders.show', $this->visible))->assertOk();
        $this->get(route('admin.orders.edit', $this->visible))->assertOk();
    }

    public function test_with_showing_orders_hidden_orders_are_listed_and_manageable(): void
    {
        $this->actingAs($this->staff(self::PERMS, true));

        $this->get(route('admin.orders.index'))->assertOk()->assertSee('800001')->assertSee('800002');
        $this->get(route('admin.orders.show', $this->hidden))->assertOk()->assertSee('No (Excluded)');
        $this->get(route('admin.orders.edit', $this->hidden))->assertOk();
        $this->post(route('admin.orders.notes.store', $this->hidden), ['body' => 'checked'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('activity_log', ['log_name' => 'order_notes', 'subject_id' => $this->hidden->id]);

        $this->delete(route('admin.orders.destroy', $this->hidden))->assertRedirect(route('admin.orders.index'));
        $this->assertSoftDeleted($this->hidden);
        $this->get(route('admin.orders.deleted'))->assertSee('800002');
    }

    public function test_customer_pages_do_not_leak_hidden_orders(): void
    {
        $viewer = $this->staff(['orders.view', 'attendees.view'], false);
        $this->actingAs($viewer);
        $this->get(route('admin.customers.show', $this->customer))
            ->assertOk()
            ->assertViewHas('customer', fn (Customer $c) => $c->orders->pluck('order_number')->all() === ['800001']);

        $allowed = $this->staff(['orders.view', 'attendees.view'], true);
        $this->actingAs($allowed);
        $this->get(route('admin.customers.show', $this->customer))
            ->assertViewHas('customer', fn (Customer $c) => $c->orders->count() === 2);
    }

    public function test_order_model_visibility_rules(): void
    {
        $without = $this->staff(['orders.view'], false);
        $with = $this->staff(['orders.view'], true);

        $this->assertFalse($this->hidden->isVisibleTo($without));
        $this->assertTrue($this->hidden->isVisibleTo($with));
        $this->assertTrue($this->visible->isVisibleTo($without));
        $this->assertFalse($this->hidden->isVisibleTo(null));
        $this->assertSame(['800001'], Order::visibleTo($without)->pluck('order_number')->all());
        $this->assertCount(2, Order::visibleTo($with)->get());
    }
}
