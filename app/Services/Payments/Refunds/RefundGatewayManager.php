<?php

namespace App\Services\Payments\Refunds;

class RefundGatewayManager
{
    /** Only providers with a documented refund API appear here (Fawaterak deliberately does not). */
    private const GATEWAYS = ['paymob' => PaymobRefundGateway::class, 'fake' => FakeRefundGateway::class];

    public function for(string $provider): ?RefundGateway
    {
        $class = self::GATEWAYS[$provider] ?? null;

        return $class ? app($class) : null;
    }
}
