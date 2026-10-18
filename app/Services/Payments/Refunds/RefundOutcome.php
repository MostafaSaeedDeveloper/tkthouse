<?php

namespace App\Services\Payments\Refunds;

final class RefundOutcome
{
    public const ACCEPTED = 'accepted';     // provider took the request; the money movement is not confirmed yet
    public const SUCCEEDED = 'succeeded';   // positive evidence the refund completed
    public const FAILED = 'failed';         // positive evidence the refund did not happen
    public const REJECTED = 'rejected';     // provider refused the request outright (definitive 4xx)
    public const PENDING = 'pending';       // still processing
    public const UNKNOWN = 'unknown';       // cannot tell

    public function __construct(
        public readonly string $status,
        public readonly ?string $providerRefundId = null,
        public readonly ?string $message = null,   // safe, short
        public readonly array $evidence = [],      // whitelisted facts only
    ) {
    }
}
