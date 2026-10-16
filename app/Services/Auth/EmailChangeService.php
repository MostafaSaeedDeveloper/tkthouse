<?php

namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Changing an account's e-mail changes who owns its orders and tickets, so the new mailbox has to prove itself with a
 * code first (single use, 10 minutes, 5 attempts, per account). Nothing changes before the code is accepted, and the
 * answer never says whether the address already belongs to someone else.
 */
class EmailChangeService
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    /** @return string sent|limited */
    public function request(User $user, string $newEmail): string
    {
        $key = 'email-change-req:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return 'limited';
        }
        RateLimiter::hit($key, 3600);

        $email = EmailOtpService::normalize($newEmail);
        $taken = EmailOtpService::findUser($email);

        if ($email === EmailOtpService::normalize((string) $user->email) || ($taken && $taken->id !== $user->id)) {
            Cache::forget($this->cacheKey($user)); // same generic answer, nothing is sent

            return 'sent';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($user), ['email' => $email, 'hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));
        Mail::to($email)->send(new LoginOtpMail($code));

        return 'sent';
    }

    public function pendingEmail(User $user): ?string
    {
        return Cache::get($this->cacheKey($user))['email'] ?? null;
    }

    /** True once the code was right and the address switched. */
    public function confirm(User $user, string $code): bool
    {
        $record = Cache::get($this->cacheKey($user));
        if (! is_array($record)) {
            return false;
        }

        if (($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($this->cacheKey($user));

            return false;
        }

        if (! preg_match('/^\d{6}$/', $code) || ! Hash::check($code, (string) $record['hash'])) {
            $record['attempts'] = ($record['attempts'] ?? 0) + 1;
            Cache::put($this->cacheKey($user), $record, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        Cache::forget($this->cacheKey($user));

        $taken = EmailOtpService::findUser($record['email']);
        if ($taken && $taken->id !== $user->id) {
            return false; // somebody claimed it in the meantime
        }

        $user->forceFill(['email' => $record['email'], 'email_verified_at' => now()])->save();

        return true;
    }

    private function cacheKey(User $user): string
    {
        return 'email-change:'.$user->id;
    }
}
