<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AffiliateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_code_is_saved_and_attached_on_registration(): void
    {
        $affiliate = User::factory()->create([
            'affiliate_code' => 'AFFTEST1',
        ]);

        $this->get('/?ref=AFFTEST1')->assertOk();

        $response = $this->post(route('front.customer.register.store'), [
            'name' => 'Referral Buyer',
            'email' => 'ref-buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('front.account.dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'ref-buyer@example.com',
            'referred_by_user_id' => $affiliate->id,
        ]);
    }

    public function test_admin_can_open_create_form_and_generate_affiliate_link_for_selected_customer(): void
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create(['affiliate_code' => null]);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.create'))
            ->assertOk()
            ->assertSee('Add Affiliate Link')
            ->assertSee($targetUser->email);

        $this->actingAs($admin)
            ->post(route('admin.affiliates.store'), [
                'user_id' => $targetUser->id,
                'target_url' => '/events/my-event',
            ])
            ->assertRedirect(route('admin.affiliates.show', $targetUser));

        $fresh = $targetUser->fresh();
        $this->assertNotNull($fresh->affiliate_code);
        $this->assertSame('/events/my-event', $fresh->affiliate_target_url);
    }

    public function test_affiliate_index_lists_only_users_with_generated_links(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(['affiliate_code' => null, 'name' => 'No Link User']);
        User::factory()->create(['affiliate_code' => 'CODE1234', 'affiliate_target_url' => '/events', 'name' => 'With Link User']);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.index'))
            ->assertOk()
            ->assertSee('With Link User')
            ->assertDontSee('No Link User');
    }

    public function test_affiliate_show_stats_match_referred_users_and_orders(): void
    {
        $admin = User::factory()->create();
        $affiliate = User::factory()->create([
            'affiliate_code' => 'AFFSTAT1',
            'affiliate_target_url' => '/about',
        ]);

        $buyer = User::factory()->create([
            'referred_by_user_id' => $affiliate->id,
        ]);

        $customer = \App\Models\Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.com',
            'phone' => '01000000000',
        ]);

        \App\Models\Order::create([
            'customer_id' => $customer->id,
            'user_id' => $buyer->id,
            'affiliate_user_id' => $affiliate->id,
            'order_number' => '556677',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 125.50,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Referred Users')
            ->assertSee('1')
            ->assertSee('Orders')
            ->assertSee('Paid Orders')
            ->assertSee('125.50 EGP');
    }

    public function test_login_and_register_keep_checkout_redirect_when_redirect_to_is_present(): void
    {
        $user = User::factory()->create([
            'username' => 'checkout_user',
            'password' => bcrypt('password123'),
        ]);

        $this->post(route('front.customer.login.store'), [
            'login' => 'checkout_user',
            'password' => 'password123',
            'redirect_to' => '/checkout',
        ])->assertRedirect('/checkout')
            ->assertSessionHas('success');

        Auth::logout();

        $this->post(route('front.customer.register.store'), [
            'name' => 'Checkout Register',
            'email' => 'checkout-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'redirect_to' => '/checkout',
        ])->assertRedirect('/checkout')
            ->assertSessionHas('success');
    }

    public function test_login_supports_ajax_response_with_redirect(): void
    {
        User::factory()->create([
            'username' => 'ajax_user',
            'email' => 'ajax-user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson(route('front.customer.login.store'), [
            'login' => 'ajax_user',
            'password' => 'password123',
            'redirect_to' => '/checkout',
        ])->assertOk()
            ->assertJson([
                'redirect_to' => '/checkout',
            ])
            ->assertJsonStructure(['message']);
    }

    public function test_register_supports_ajax_response_with_redirect(): void
    {
        $this->postJson(route('front.customer.register.store'), [
            'name' => 'Ajax Register',
            'email' => 'ajax-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'redirect_to' => '/checkout',
        ])->assertOk()
            ->assertJson([
                'redirect_to' => '/checkout',
            ])
            ->assertJsonStructure(['message']);
    }
}
