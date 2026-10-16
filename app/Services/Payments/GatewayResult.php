<?php

namespace App\Services\Payments;

/** Outcome of asking a gateway (server to server) about one payment. */
final class GatewayResult
{
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const PENDING = 'pending';
    /** Could not be determined (network error, unexpected response). Never treated as paid or failed. */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function unknown(string $message): self
    {
        return new self(self::UNKNOWN, message: $message);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
