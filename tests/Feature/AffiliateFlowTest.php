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
            ->post(route('admin.affiliates.store'), ['user_id' => $targetUser->id])
            ->assertRedirect(route('admin.affiliates.show', $targetUser));

        $this->assertNotNull($targetUser->fresh()->affiliate_code);
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
}
