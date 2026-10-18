<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

/**
 * A role has two names: `name` is the stable KEY the code and seeders refer to (never shown as a title, never renamed),
 * `display_name` is what people read and may be edited freely.
 */
final class RoleLabel
{
    /** Roles the application itself relies on: key => [label, description]. They cannot be deleted. */
    public const SYSTEM = [
        'super_admin' => ['Super Admin', 'Full access, including payment accounts and protected settings. Keep this to one or two people.'],
        'admin' => ['Administrator', 'Everything in the panel: events, tickets, orders, payments, users, roles and settings.'],
        'event_manager' => ['Event Manager', 'Creates and edits events, ticket types, fees and images; sees orders and attendees.'],
        'ticket_manager' => ['Ticket Manager', 'Manages tickets and orders (update, delete, restore), sees promo codes and attendees, uses the scanner.'],
        'support' => ['Support', 'Read-only: dashboard, events, orders, reports, attendees and activity logs.'],
        'scanner' => ['Scanner Staff', 'Gate staff: scans tickets and sees the basic ticket and order information.'],
        'Order Manager' => ['Order Manager', 'Assigns and reassigns orders, sees every order and the agents\' statistics.'],
        'Order Agent' => ['Order Agent', 'Works only the orders assigned to them.'],
        'agent' => ['Agent', 'Sales/booking agent: works only the orders assigned to them, sees their own transactions and stats. Turn on "Can receive assigned orders" on the user to include them in automatic assignment.'],
    ];

    public static function of(Role|string|null $role): string
    {
        if ($role === null || $role === '') {
            return '';
        }

        $name = $role instanceof Role ? $role->name : $role;
        $display = $role instanceof Role ? trim((string) ($role->display_name ?? '')) : '';

        return $display !== '' ? $display : (self::SYSTEM[$name][0] ?? str($name)->replace(['_', '-'], ' ')->headline()->toString());
    }

    public static function isSystem(Role|string $role): bool
    {
        $name = $role instanceof Role ? $role->name : $role;

        return isset(self::SYSTEM[$name]) || ($role instanceof Role && (bool) ($role->is_system ?? false));
    }
}
