<?php

/*
| Outbound customer notifications (mail / WhatsApp) guard for dev & staging.
|
| A staging site that carries a copy of production data must never message real customers. Set these
| there; leave them unset in production (no restriction).
|
|   NOTIFY_MAIL_ALLOWLIST=none                       → block every email
|   NOTIFY_MAIL_ALLOWLIST=qa@tkthouse.com,@tkthouse.com → only these addresses / @domains
|   NOTIFY_WHATSAPP_ENABLED=false                    → never call the WhatsApp provider
|   NOTIFY_PHONE_ALLOWLIST=+201000000000             → only these numbers (comma separated, or "none")
*/

$list = function (?string $value): ?array {
    $value = trim((string) $value);

    if ($value === '') {
        return null; // unset: no restriction
    }

    if (strtolower($value) === 'none') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
};

return [
    'mail_allowlist' => $list(env('NOTIFY_MAIL_ALLOWLIST')),
    'whatsapp_enabled' => filter_var(env('NOTIFY_WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'phone_allowlist' => $list(env('NOTIFY_PHONE_ALLOWLIST')),
];
