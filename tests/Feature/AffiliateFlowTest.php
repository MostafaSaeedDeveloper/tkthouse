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

    public function test_admin_can_generate_affiliate_link_for_user(): void
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create(['affiliate_code' => null]);

        $this->actingAs($admin)
            ->post(route('admin.affiliates.generate-link', $targetUser))
            ->assertRedirect();

        $this->assertNotNull($targetUser->fresh()->affiliate_code);
    }
}
