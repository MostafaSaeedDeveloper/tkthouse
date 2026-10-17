<?php

namespace App\Services\Payments\Refunds;

final class RefundCapability
{
    public const API = 'api';
    public const MANUAL = 'manual';
    public const NONE = 'none';

    public function __construct(
        public readonly string $mode,
        public readonly ?string $note = null,   // why API is not used / why nothing is possible (shown to staff)
        public readonly bool $canVoid = false,
    ) {
    }

    public function isApi(): bool
    {
        return $this->mode === self::API;
    }
}
