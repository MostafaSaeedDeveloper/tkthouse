<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\PaymentMethod;

class SystemSettings
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => static::encode($value)]);
        static::$cache = null;
    }

    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        static::$cache = SystemSetting::query()
            ->get(['key', 'value'])
            ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => static::decode($setting->value)])
            ->all();

        return static::$cache;
    }

    public static function paymentMethods(): array
    {
        return PaymentMethod::activeCodes();
    }

    private static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private static function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $json = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }
}
