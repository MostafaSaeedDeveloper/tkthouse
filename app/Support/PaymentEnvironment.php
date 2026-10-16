<?php

namespace App\Support;

use RuntimeException;

/**
 * Environment safety rules for payments, in one place.
 *
 *  - The fake gateway can never run in production.
 *  - Real gateways (and the credentials copied into a staging database) are only contacted when the
 *    environment explicitly allows it. Production allows it by default, every other environment denies it
 *    until PAYMENTS_LIVE_GATEWAYS=true is set — and that must then point at *sandbox* credentials.
 *  - Paymob webhook signatures are mandatory in production.
 *  - Fawaterak stays off in production until PAYMENTS_FAWATERAK_VERIFIED=true (set only after a sandbox run).
 */
final class PaymentEnvironment
{
    public static function isProduction(): bool
    {
        return app()->isProduction();
    }

    public static function fakeGatewayAllowed(): bool
    {
        return ! self::isProduction() && (bool) config('payments.fake.enabled');
    }

    public static function liveGatewaysAllowed(): bool
    {
        return self::isProduction() || (bool) config('payments.live_gateways.enabled');
    }

    public static function paymobSignatureRequired(): bool
    {
        return self::isProduction() || (bool) config('payments.paymob.require_hmac');
    }

    public static function fawaterakAllowed(): bool
    {
        if (! self::liveGatewaysAllowed()) {
            return false;
        }

        return ! self::isProduction() || (bool) config('payments.fawaterak.verified');
    }

    public static function assertLiveGatewaysAllowed(string $provider): void
    {
        if (! self::liveGatewaysAllowed()) {
            throw new RuntimeException("Live payment gateways are disabled in this environment ({$provider}). Set PAYMENTS_LIVE_GATEWAYS=true with sandbox credentials to test.");
        }
    }
}
