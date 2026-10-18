<?php

namespace App\Services\Payments\Routing;

use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\User;
use App\Support\PaymentEnvironment;
use App\Support\SystemSettings;

/**
 * The single place that decides which merchant account takes a payment. Server-side only: nothing the browser sends is an input.
 * Both features are independent switches and both default to off/empty, in which case every answer is "legacy".
 */
class PaymentAccountResolver
{
    public static function routingEnabled(): bool
    {
        return (bool) SystemSettings::get('payment_routing_enabled', false);
    }

    public static function blockOnlinePolicy(): bool
    {
        return (string) SystemSettings::get('unassigned_order_payment_policy', 'default_account') === 'block_online';
    }

    /** Provider-level gates plus enabled+verified+not archived. */
    public static function usable(?IntegrationAccount $account): bool
    {
        if (! $account || ! $account->isUsable() || ! in_array($account->provider, ['paymob', 'fawaterak', 'fake'], true)) {
            return false;
        }
        if ($account->provider === 'fake') {
            return PaymentEnvironment::fakeGatewayAllowed();
        }
        if (! PaymentEnvironment::liveGatewaysAllowed()) {
            return false;
        }

        return $account->provider !== 'fawaterak' || PaymentEnvironment::fawaterakAllowed();
    }

    public function defaultAccountId(): ?int
    {
        $id = (int) SystemSettings::get('default_payment_account_id', 0);

        return $id > 0 ? $id : null;
    }

    /** Default policy for an order with no agent-specific account. */
    public function defaultPolicy(?int $assigneeId = null, ?array $allowed = null): Resolution
    {
        if ($id = $this->defaultAccountId()) {
            $account = IntegrationAccount::query()->find($id);

            return self::usable($account)
                ? new Resolution($account, 'default_account', assigneeId: $assigneeId, allowedMethods: $allowed)
                : new Resolution(null, 'blocked', true, 'default_account_unavailable', $assigneeId, $allowed);
        }

        return new Resolution(null, 'legacy', assigneeId: $assigneeId, allowedMethods: $allowed);
    }

    /**
     * Payment methods the assigned agent's customers may choose (null = no restriction = every active method). One merchant
     * account serves everyone: agents only narrow which METHODS are shown, never which account collects. The restriction only
     * applies while the routing switch is on, so turning that switch off always restores "everything for everyone".
     */
    public function allowedMethodsFor(?User $assignee): ?array
    {
        if (! self::routingEnabled() || ! $assignee) {
            return null;
        }

        $allowed = $assignee->allowed_payment_methods;

        return is_array($allowed) && $allowed ? array_values($allowed) : null;
    }

    /** What a *new* routing decision looks like for an agent (or none). Used by reservations and fresh orders. */
    public function forAssignee(?User $assignee): Resolution
    {
        // Routing off: only the default merchant account (if any) matters — agents and the unassigned policy are ignored.
        if (! self::routingEnabled()) {
            return $this->defaultPolicy($assignee?->id);
        }

        if (! $assignee) {
            return self::blockOnlinePolicy()
                ? new Resolution(null, 'blocked', true, 'unassigned_policy_blocks_online')
                : $this->defaultPolicy();
        }

        return $this->defaultPolicy($assignee->id, $this->allowedMethodsFor($assignee));
    }

    /**
     * The account for opening a payment on an EXISTING order. Order of precedence: the account the order is pinned to, then the
     * account of its latest attempt (so a retry never silently moves to another merchant), then a fresh decision.
     */
    public function forOrder(Order $order): Resolution
    {
        // The methods follow the order's CURRENT assignee, whichever account the order is pinned to.
        $allowed = $this->allowedMethodsFor($order->assigned_user_id ? User::query()->find($order->assigned_user_id) : null);

        if ($order->integration_account_id) {
            $account = $order->integrationAccount;

            return self::usable($account)
                ? new Resolution($account, 'pinned', assigneeId: $order->assigned_user_id, allowedMethods: $allowed)
                : new Resolution(null, 'blocked', true, 'pinned_account_unavailable', $order->assigned_user_id, $allowed);
        }

        $latest = $order->paymentAttempts()->orderByDesc('id')->first();
        if ($latest) {
            // Sticky: legacy attempts (NULL) keep the legacy path; account attempts keep their account (history stays verifiable even if disabled).
            return $latest->integration_account_id
                ? (self::usable($latest->integrationAccount)
                    ? new Resolution($latest->integrationAccount, 'sticky', assigneeId: $order->assigned_user_id, allowedMethods: $allowed)
                    : new Resolution(null, 'blocked', true, 'attempt_account_unavailable', $order->assigned_user_id, $allowed))
                : new Resolution(null, 'legacy', assigneeId: $order->assigned_user_id, allowedMethods: $allowed);
        }

        return $this->forAssignee($order->assigned_user_id ? User::query()->find($order->assigned_user_id) : null);
    }
}
