<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\PaymentMethod;

class SystemSettings
{
    private static function service(): \App\Settings\SettingsService
    {
        return app(\App\Settings\SettingsService::class);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::service()->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::service()->set($key, $value);
    }

    public static function all(): array
    {
        return static::service()->all();
    }

    public static function paymentMethods(): array
    {
        return PaymentMethod::activeCodes();
    }

    public static function pendingPaymentTimeoutMinutes(): int
    {
        return max(1, (int) static::get('pending_payment_timeout_minutes', 60 * 24 * 2));
    }
}
