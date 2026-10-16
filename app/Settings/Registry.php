<?php

namespace App\Settings;

/**
 * Single source of truth for manageable business settings. A key that is not declared here can never be written
 * through the Settings Center. Secrets are NOT settings: they live in encrypted integration accounts.
 *
 * type: string|text|bool|int|color|select|file
 */
final class Registry
{
    public const GROUPS = [
        'general' => ['label' => 'General', 'icon' => 'fa-sliders', 'permission' => 'settings.view'],
        'branding' => ['label' => 'Branding', 'icon' => 'fa-palette', 'permission' => 'settings.view'],
        'events' => ['label' => 'Events & Tickets', 'icon' => 'fa-ticket', 'permission' => 'settings.view'],
        'orders' => ['label' => 'Orders', 'icon' => 'fa-receipt', 'permission' => 'settings.orders'],
        'payments' => ['label' => 'Payment Routing', 'icon' => 'fa-credit-card', 'permission' => 'settings.payment-accounts.view'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'fa-bell', 'permission' => 'settings.view'],
        'security' => ['label' => 'Security & Access', 'icon' => 'fa-shield-halved', 'permission' => 'settings.security.view'],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            // ---- General
            'site_name' => ['group' => 'general', 'type' => 'string', 'label' => 'Website name', 'default' => 'TKT House', 'rules' => ['required', 'string', 'max:120']],
            'contact_email' => ['group' => 'general', 'type' => 'string', 'label' => 'Contact e-mail', 'default' => null, 'rules' => ['nullable', 'email', 'max:190']],
            'contact_phone' => ['group' => 'general', 'type' => 'string', 'label' => 'Contact phone', 'default' => null, 'rules' => ['nullable', 'string', 'max:40']],
            'contact_address' => ['group' => 'general', 'type' => 'text', 'label' => 'Address', 'default' => null, 'rules' => ['nullable', 'string', 'max:500']],
            'social_instagram' => ['group' => 'general', 'type' => 'string', 'label' => 'Instagram URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
            'social_facebook' => ['group' => 'general', 'type' => 'string', 'label' => 'Facebook URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
            'social_tiktok' => ['group' => 'general', 'type' => 'string', 'label' => 'TikTok URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
            'social_x' => ['group' => 'general', 'type' => 'string', 'label' => 'X (Twitter) URL', 'default' => null, 'rules' => ['nullable', 'url', 'max:255']],
            'default_currency_label' => ['group' => 'general', 'type' => 'select', 'label' => 'Display currency label', 'default' => 'EGP', 'options' => ['EGP' => 'EGP', 'USD' => 'USD'],
                'help' => 'Display only. Payments are charged in the currency configured on the gateway; changing this never changes amounts.', 'rules' => ['required', 'in:EGP,USD']],
            'frontend_maintenance' => ['group' => 'general', 'type' => 'bool', 'label' => 'Put the public website in maintenance mode', 'default' => false,
                'help' => 'Shows the maintenance page to visitors. The admin dashboard, scanner and payment callbacks stay online.', 'rules' => ['boolean']],

            // ---- Branding
            'primary_color' => ['group' => 'branding', 'type' => 'color', 'label' => 'Frontend primary color', 'default' => '#f5b800', 'rules' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/']],
            'secondary_color' => ['group' => 'branding', 'type' => 'color', 'label' => 'Frontend secondary color', 'default' => '#111111', 'rules' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/']],
            'site_logo_light' => ['group' => 'branding', 'type' => 'file', 'label' => 'Logo (light background)', 'default' => null, 'rules' => ['nullable', 'image', 'max:2048']],
            'site_logo_dark' => ['group' => 'branding', 'type' => 'file', 'label' => 'Logo (dark background)', 'default' => null, 'rules' => ['nullable', 'image', 'max:2048']],
            'site_logo_footer' => ['group' => 'branding', 'type' => 'file', 'label' => 'Footer logo', 'default' => null, 'rules' => ['nullable', 'image', 'max:2048']],
            'site_favicon' => ['group' => 'branding', 'type' => 'file', 'label' => 'Favicon', 'default' => null, 'rules' => ['nullable', 'image', 'max:512']],

            // ---- Events & tickets
            'pending_payment_timeout_minutes' => ['group' => 'events', 'type' => 'int', 'label' => 'Booking expiration (minutes)', 'default' => 2880,
                'help' => 'How long an unpaid order is held before it expires. Orders already created keep their own deadline.', 'rules' => ['required', 'integer', 'min:1', 'max:10080']],
            'default_max_tickets_per_order' => ['group' => 'events', 'type' => 'int', 'label' => 'Default max tickets per order', 'default' => 10,
                'help' => 'Used only for ticket types that do not define their own limit; a ticket\'s own limit always wins.', 'rules' => ['required', 'integer', 'min:1', 'max:100']],

            // ---- Orders (assignment keys are consumed by Stage D)
            'order_assignment_enabled' => ['group' => 'orders', 'type' => 'bool', 'label' => 'Enable order assignment', 'default' => false, 'rules' => ['boolean']],
            'order_assignment_mode' => ['group' => 'orders', 'type' => 'select', 'label' => 'Assignment mode', 'default' => 'manual',
                'options' => ['manual' => 'Manual', 'round_robin' => 'Automatic — round robin', 'default_assignee' => 'Default assignee'], 'rules' => ['required', 'in:manual,round_robin,default_assignee']],
            'order_default_assignee_id' => ['group' => 'orders', 'type' => 'user', 'label' => 'Default assignee', 'default' => null, 'rules' => ['nullable', 'integer']],

            // ---- Payment routing (all off / empty by default => today's payment behaviour is unchanged)
            'payment_routing_enabled' => ['group' => 'payments', 'type' => 'bool', 'label' => 'Enable per-user payment routing', 'default' => false,
                'help' => 'When on, an agent with a custom merchant account collects the payments of the orders shown to their customers. Existing payment attempts are never affected.', 'rules' => ['boolean']],
            'default_payment_account_id' => ['group' => 'payments', 'type' => 'account', 'label' => 'Default merchant account', 'default' => null,
                'help' => 'Used when no agent-specific account applies. Leave empty to keep using the existing payment-method configuration.', 'rules' => ['nullable', 'integer']],
            'unassigned_order_payment_policy' => ['group' => 'payments', 'type' => 'select', 'label' => 'Unassigned order payment policy', 'default' => 'default_account',
                'options' => ['default_account' => 'Use the default merchant account', 'block_online' => 'Block online payment (manual confirmation only)'], 'rules' => ['required', 'in:default_account,block_online']],

            // ---- Notifications
            'whatsapp_ticket_sending_enabled' => ['group' => 'notifications', 'type' => 'bool', 'label' => 'Send tickets via WhatsApp', 'default' => true,
                'help' => 'Disable to stop WhatsApp sending from admin and post-payment flows.', 'rules' => ['boolean']],
        ];
    }

    public static function definition(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function forGroup(string $group): array
    {
        return array_filter(self::all(), fn ($d) => $d['group'] === $group);
    }
}
