<?php

namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Short-lived e-mail one-time codes proving that the person at this browser can read an account's mailbox.
 *
 *  - 6 digits, 10 minutes, single use, stored hashed, bound to the browser session that asked for it
 *  - at most 5 wrong guesses per code, 30 s between sends, 5 sends / hour / e-mail, 30 sends / hour / IP
 *  - send() never reveals whether the address has an account
 */
class EmailOtpService
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const COOLDOWN_SECONDS = 30;

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function findUser(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [self::normalize($email)])->first();
    }

    /** @return string one of: sent | skipped | cooldown | limited */
    public function send(string $email, string $sessionId, ?string $ip): string
    {
        $email = self::normalize($email);
        $key = $this->key($email);

        if (Cache::has($key.':cooldown')) {
            return 'cooldown';
        }

        if (RateLimiter::tooManyAttempts('otp-email:'.sha1($email), 5) || RateLimiter::tooManyAttempts('otp-ip:'.($ip ?? 'x'), 30)) {
            return 'limited';
        }
        RateLimiter::hit('otp-email:'.sha1($email), 3600);
        RateLimiter::hit('otp-ip:'.($ip ?? 'x'), 3600);
        Cache::put($key.':cooldown', 1, self::COOLDOWN_SECONDS);

        $user = self::findUser($email);
        if (! $user) {
            return 'skipped'; // same response as "sent": no account enumeration here
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($key, [
            'hash' => Hash::make($code),
            'session' => $sessionId,
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Mail::to($user->email)->send(new LoginOtpMail($code));

        return 'sent';
    }

    /** Returns the verified user, or null (wrong/expired/used code, other browser, too many tries). */
    public function verify(string $email, string $code, string $sessionId): ?User
    {
        $email = self::normalize($email);
        $key = $this->key($email);
        $record = Cache::get($key);

        if (! is_array($record) || ! hash_equals((string) $record['session'], $sessionId)) {
            return null;
        }

        if (($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return null;
        }

        if (! preg_match('/^\d{6}$/', $code) || ! Hash::check($code, (string) $record['hash'])) {
            $record['attempts'] = ($record['attempts'] ?? 0) + 1;
            Cache::put($key, $record, now()->addMinutes(self::TTL_MINUTES));

            return null;
        }

        Cache::forget($key); // single use

        return self::findUser($email);
    }

    private function key(string $email): string
    {
        return 'email-otp:'.sha1($email);
    }
}
