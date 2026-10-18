<?php

namespace Tests\Feature;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class PaymentDataValidationTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_validation_is_read_only_by_default_and_backfill_touches_only_the_new_column(): void
    {
        $order = $this->makePendingOrder(User::factory()->create(), null, 1, 100, 'paymob_card');
        $legacy = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'reference' => 'R-1', 'transaction_id' => 'LEGACY-TX', 'amount' => 100, 'currency' => 'EGP', 'status' => 'paid']);
        $before = $legacy->fresh()->only(['transaction_id', 'reference', 'amount', 'status', 'integration_account_id', 'method_code']);

        $this->artisan('payments:validate')->assertSuccessful();
        $this->assertNull($legacy->fresh()->internal_reference, 'dry run writes nothing');

        $this->artisan('payments:validate --backfill-references')->assertSuccessful();
        $legacy->refresh();
        $this->assertMatchesRegularExpression('/^PAY-/', $legacy->internal_reference);
        $this->assertSame($before, $legacy->only(['transaction_id', 'reference', 'amount', 'status', 'integration_account_id', 'method_code']));
        $this->assertSame($legacy->internal_reference, tap($legacy)->refresh()->internal_reference, 'idempotent');
    }

    public function test_validation_flags_real_problems(): void
    {
        $order = $this->makePendingOrder(User::factory()->create(), null, 1, 100, 'paymob_card');
        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 100, 'currency' => 'EGP', 'status' => 'paid']); // paid without transaction id

        $this->artisan('payments:validate')->assertFailed();
    }
}
