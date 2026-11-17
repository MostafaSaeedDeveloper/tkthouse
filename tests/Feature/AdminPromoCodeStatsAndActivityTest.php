<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminPromoCodeStatsAndActivityTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $perms): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    private function order(PromoCode $promo, string $status, float $subtotal, float $discount, array $extra = []): Order
    {
        $customer = Customer::firstOrCreate(['email' => 'promo-stats@example.com'], ['first_name' => 'P', 'last_name' => 'S', 'phone' => '01000000000']);

        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'order_number' => (string) random_int(100000, 999999),
            'status' => $status,
            'requires_approval' => false,
            'payment_method' => 'cash',
            'promo_code_id' => $promo->id,
            'promo_code' => $promo->code,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => $subtotal - $discount,
            'paid_at' => $status === 'paid' ? now() : null,
        ], $extra));
    }

    public function test_creating_updating_and_deleting_a_code_is_logged_with_the_actor(): void
    {
        $admin = $this->user(['dashboard.view', 'promo-codes.view', 'promo-codes.create', 'promo-codes.update', 'promo-codes.delete', 'activity-logs.view']);

        $this->actingAs($admin)->post(route('admin.promo-codes.store'), ['code' => 'save10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => 1])->assertRedirect();
        $promo = PromoCode::where('code', 'SAVE10')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.promo-codes.update', $promo), ['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 25, 'is_active' => 1])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.promo-codes.destroy', $promo))->assertRedirect();

        $events = Activity::where('log_name', 'promo_codes')->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $events->pluck('event')->all());
        $this->assertTrue($events->every(fn ($a) => $a->causer_id === $admin->id));
        $this->assertSame('Promo code SAVE10 deleted', $events->last()->description);
        $this->assertEquals(25, $events[1]->properties['attributes']['discount_value']);
        $this->assertEquals(10, $events[1]->properties['old']['discount_value']);
    }

    public function test_usage_counter_changes_do_not_flood_the_log(): void
    {
        $promo = PromoCode::create(['code' => 'QUIET', 'discount_type' => 'percent', 'discount_value' => 5, 'is_active' => true]);
        $before = Activity::where('log_name', 'promo_codes')->count();

        $promo->increment('used_count');
        $promo->update(['used_count' => 5]);

        $this->assertSame($before, Activity::where('log_name', 'promo_codes')->count());
    }

    public function test_page_shows_stats_counting_only_paid_orders_that_count_toward_statistics(): void
    {
        $promo = PromoCode::create(['code' => 'STATS20', 'discount_type' => 'percent', 'discount_value' => 20, 'is_active' => true, 'used_count' => 3]);

        $this->order($promo, 'paid', 1000, 200);
        $this->order($promo, 'paid', 500, 100);
        $this->order($promo, 'pending', 900, 180);                                    // not paid
        $this->order($promo, 'paid', 700, 140, ['exclude_from_statistics' => true]);  // hidden from statistics

        $admin = $this->user(['dashboard.view', 'promo-codes.view']);
        $response = $this->actingAs($admin)->get(route('admin.promo-codes.index'))->assertOk();

        $stats = $response->viewData('perCode')->get($promo->id);
        $this->assertSame(2, (int) $stats->orders);
        $this->assertEquals(300, $stats->discount);
        $this->assertEquals(1200, $stats->revenue); // (1000-200) + (500-100)
        $response->assertViewHas('totals', fn ($t) => $t['codes'] === 1 && $t['redemptions'] === 3 && $t['orders'] === 2);
        $response->assertSee('Discount given')->assertSee('300.00 EGP');
    }

    public function test_activity_panel_needs_the_activity_logs_permission(): void
    {
        PromoCode::create(['code' => 'SEEN', 'discount_type' => 'fixed', 'discount_value' => 50, 'is_active' => true]);

        $without = $this->user(['dashboard.view', 'promo-codes.view']);
        $this->actingAs($without)->get(route('admin.promo-codes.index'))->assertOk()->assertDontSee('Recent activity')->assertDontSee('Promo code SEEN created');

        $with = $this->user(['dashboard.view', 'promo-codes.view', 'activity-logs.view']);
        $this->actingAs($with)->get(route('admin.promo-codes.index'))->assertOk()->assertSee('Recent activity')->assertSee('Promo code SEEN created');
    }

    public function test_activity_log_page_can_be_filtered_by_log_name(): void
    {
        PromoCode::create(['code' => 'ONLYME', 'discount_type' => 'fixed', 'discount_value' => 50, 'is_active' => true]);
        activity('settings')->log('Settings updated');

        $admin = $this->user(['dashboard.view', 'activity-logs.view']);
        $this->actingAs($admin)->get(route('admin.activity-logs.index', ['log' => 'promo_codes']))
            ->assertOk()->assertSee('Promo code ONLYME created')->assertDontSee('Settings updated');
    }
}
