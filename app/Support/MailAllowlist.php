<?php

namespace App\Support;

/** Mirrors the dev/staging mail safety net (config/notifications.php) so a message that the net would silently cancel is never recorded as "sent". */
class MailAllowlist
{
    public static function allows(string $email): bool
    {
        $allowlist = config('notifications.mail_allowlist');
        if ($allowlist === null) {
            return true;   // production: no restriction
        }

        return collect($allowlist)->contains(fn (string $rule) => str_starts_with($rule, '@')
            ? str_ends_with(strtolower($email), strtolower($rule))
            : strcasecmp($email, $rule) === 0);
    }
}
