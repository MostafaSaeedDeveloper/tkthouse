<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Mail\RegisterOtpMail;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /** A back-office user holding the seeded `admin` role (the admin panel rejects plain users with 403). */
    protected function createAdmin(array $attributes = []): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create($attributes);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Drives the real two-step customer sign-up (request OTP by email, then verify it) and
     * returns the verification response.
     */
    protected function registerCustomerWithOtp(array $data, bool $json = false): TestResponse
    {
        Mail::fake();

        $this->postJson(route('front.customer.register.request-otp'), $data)->assertOk()->assertJson(['otp_required' => true]);

        $code = null;
        Mail::assertSent(RegisterOtpMail::class, function (RegisterOtpMail $mail) use (&$code) {
            $code = $mail->otpCode;

            return true;
        });

        $payload = ['otp' => $code, 'redirect_to' => $data['redirect_to'] ?? null];

        return $json
            ? $this->postJson(route('front.customer.register.verify-otp'), $payload)
            : $this->post(route('front.customer.register.verify-otp'), $payload);
    }
}
