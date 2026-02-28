<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response->assertRedirect(route('front.account.profile'));

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
}
