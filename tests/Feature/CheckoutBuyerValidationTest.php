<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutBuyerValidationTest extends TestCase
{
    use RefreshDatabase;

    private function preflight(array $overrides = [])
    {
        return $this->postJson(route('front.checkout.preflight'), array_merge([
            'full_name' => 'Nour Hassan', 'email' => 'nour@gmail.com', 'phone' => '+201000000000',
        ], $overrides));
    }

    public function test_script_payload_in_name_is_rejected(): void
    {
        $this->preflight(['full_name' => '<script>alert(1)</script> -'])->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_junk_phone_is_rejected(): void
    {
        $this->preflight(['phone' => '<b>1</b>'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_placeholder_email_domains_are_blocked(): void
    {
        config(['checkout.blocked_email_domains' => ['example.com']]);

        $this->preflight(['email' => 'research1@example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->preflight(['email' => 'research1@mail.example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
