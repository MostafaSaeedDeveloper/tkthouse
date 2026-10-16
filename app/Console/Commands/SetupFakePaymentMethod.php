<?php

namespace App\Console\Commands;

use App\Models\PaymentMethod;
use Illuminate\Console\Command;

/** Dev/staging helper: registers the simulator payment method. Refuses to run in production. */
class SetupFakePaymentMethod extends Command
{
    protected $signature = 'payments:setup-fake-method {--disable : Deactivate the simulator method instead}';

    protected $description = 'Create (or disable) the "Test gateway" payment method used to try checkout without real money.';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        if ($this->option('disable')) {
            PaymentMethod::where('code', 'fake_test')->update(['is_active' => false]);
            $this->info('Test gateway disabled.');

            return self::SUCCESS;
        }

        if (! config('payments.fake.enabled')) {
            $this->warn('PAYMENTS_FAKE_ENABLED is not true: the method will exist but will not be offered at checkout.');
        }

        PaymentMethod::updateOrCreate(['code' => 'fake_test'], [
            'name' => 'Test gateway',
            'checkout_label' => 'Test gateway (no real payment)',
            'checkout_description' => 'Simulated payment for testing only.',
            'provider' => 'fake',
            'is_active' => true,
            'config' => [],
        ]);

        $this->info('Test gateway method ready (code: fake_test).');

        return self::SUCCESS;
    }
}
