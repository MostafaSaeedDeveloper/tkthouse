<?php

namespace App\Support;

/**
 * The production permission policy for Payments. Used by the (additive) sync command, the fresh-install seeder and the tests,
 * so there is exactly one place that says who gets what. Nothing here ever REMOVES a permission.
 */
final class PaymentPermissionPolicy
{
    public const ALL = [
        'payments.transactions.view', 'payments.transactions.inquire',
        'payments.refunds.view', 'payments.refunds.create', 'payments.refunds.approve',
        'payments.accounts.view', 'payments.accounts.manage', 'payments.webhooks.view',
    ];

    /** Normalised role name (lower-case letters/digits only) => permissions granted by the policy. */
    public const ROLES = [
        'superadmin' => self::ALL,
        'admin' => self::ALL, // the seeded `admin` role already receives every permission
        // View transactions (scoped by order access), view refunds and request refunds for orders they may manage.
        // NOT: approve, merchant-account management, webhooks, provider inquiry.
        'ordermanager' => ['payments.transactions.view', 'payments.refunds.view', 'payments.refunds.create'],
        // Own orders' transactions only (scoping is enforced by OrderAccess). No refund rights by default.
        'orderagent' => ['payments.transactions.view'],
    ];

    public static function normalise(string $roleName): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $roleName));
    }

    /** @return list<string> */
    public static function forRole(string $roleName): array
    {
        return self::ROLES[self::normalise($roleName)] ?? [];
    }
}
