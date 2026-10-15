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
}
