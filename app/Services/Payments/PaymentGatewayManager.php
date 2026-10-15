<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\FawaterakGateway;
use App\Services\Payments\Gateways\PaymobGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'paymob' => PaymobGateway::class,
        'fawaterak' => FawaterakGateway::class,
        'fake' => FakeGateway::class,
    ];

    public function for(string $provider): PaymentGateway
    {
        $class = self::GATEWAYS[$provider] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider}].");
        }

        return app($class);
    }

    public function supports(string $provider): bool
    {
        return isset(self::GATEWAYS[$provider]);
    }

    /**
     * Methods a customer may pick right now. Online providers must be fully configured (fake only where enabled);
     * methods with an unknown provider are manual (confirmed by staff) and always offered, as before.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\PaymentMethod>
     */
    public function available(): \Illuminate\Support\Collection
    {
        return \App\Models\PaymentMethod::query()
            ->where('is_active', true)
            ->where('code', '!=', 'card')
            ->orderBy('id')
            ->get()
            ->filter(fn (\App\Models\PaymentMethod $m) => ! $this->supports((string) $m->provider) || $this->for((string) $m->provider)->isConfigured($m))
            ->values();
    }

    public function isOnline(\App\Models\PaymentMethod $method): bool
    {
        return $this->supports((string) $method->provider);
    }
}
