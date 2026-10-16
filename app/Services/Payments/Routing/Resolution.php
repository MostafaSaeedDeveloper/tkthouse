<?php

namespace App\Services\Payments\Routing;

use App\Models\IntegrationAccount;

/** Outcome of the merchant-account decision. account = null && ! blocked => the legacy payment-method configuration. */
final class Resolution
{
    public function __construct(
        public readonly ?IntegrationAccount $account,
        public readonly string $source,          // pinned | sticky | agent_account | default_account | legacy | blocked
        public readonly bool $blocked = false,   // online payment is not allowed for this order right now
        public readonly ?string $reason = null,
        public readonly ?int $assigneeId = null,
        public readonly ?array $allowedMethods = null, // agent-level restriction (null = no restriction)
    ) {
    }

    public function snapshot(): array
    {
        return [
            'source' => $this->source,
            'blocked' => $this->blocked,
            'reason' => $this->reason,
            'account_id' => $this->account?->id,
            'account_name' => $this->account?->name,
            'provider' => $this->account?->provider,
            'environment' => $this->account?->environment,
            'assignee_id' => $this->assigneeId,
            'routing_enabled' => PaymentAccountResolver::routingEnabled(),
            'resolved_at' => now()->toIso8601String(),
        ];
    }
}
