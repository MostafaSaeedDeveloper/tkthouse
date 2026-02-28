<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_requires_phone_and_saves_it(): void
    {
        $response = $this->post(route('front.customer.register.store'), [
            'name' => 'Test Customer',
            'email' => 'register@example.com',
            'phone' => '01012345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('front.account.dashboard'));

        $user = User::query()->where('email', 'register@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('01012345678', $user->phone);
    }
}
