<?php

namespace App\Services\Checkout;

use App\Models\User;
use App\Services\Auth\EmailOtpService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Decides which account an express-checkout order belongs to — without ever treating a typed e-mail as proof.
 *
 *  signed in            → that account (the contact details typed on the form only describe the order)
 *  new e-mail           → a passwordless account is created (no session is granted: the browser did not prove the mailbox)
 *  e-mail of an account → EmailVerificationRequired: the buyer must enter the code we mail to that address first
 *  staff e-mail         → StaffEmailNotAllowed (never reachable through this flow)
 */
class CheckoutIdentity
{
    public const STAFF_ROLES = ['admin', 'event_manager', 'ticket_manager', 'support', 'scanner', 'super_admin', 'super admin'];

    private const CREATED_KEY = 'checkout.created_user';

    /**
     * @param array{first_name: string, last_name: string, email: string, phone?: ?string} $buyer
     * @return array{0: User, 1: bool} [account, newlyCreated]
     */
    public function resolve(Request $request, array $buyer): array
    {
        if ($user = $request->user()) {
            return [$user, false];
        }

        $email = EmailOtpService::normalize($buyer['email']);

        // Serialise creation per e-mail so concurrent submits can never create two accounts.
        $lock = Cache::lock('checkout-account:'.sha1($email), 15);
        $lock->block(8);

        try {
            $existing = EmailOtpService::findUser($email);

            if ($existing) {
                // The account this very browser session created a moment ago (retry / double submit) is still "ours".
                if ($this->createdByThisSession($request, $existing)) {
                    return [$existing, false];
                }

                if ($this->isStaff($existing)) {
                    throw new StaffEmailNotAllowed();
                }

                throw new EmailVerificationRequired($existing->email);
            }

            try {
                $user = User::create([
                    'name' => trim($buyer['first_name'].' '.$buyer['last_name']),
                    'username' => $this->username($email),
                    'email' => $email,
                    'phone' => $buyer['phone'] ?? null,
                    // Nobody knows this value and it is never sent anywhere: the customer signs in with an e-mail code.
                    'password' => Str::random(64),
                    'passwordless_since' => now(),
                    'referred_by_user_id' => ((int) $request->session()->get('affiliate.referrer_id', 0)) ?: null,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another process won the race for this e-mail between our check and insert.
                throw new EmailVerificationRequired($email);
            }

            $request->session()->put(self::CREATED_KEY, ['id' => $user->id, 'at' => now()->timestamp]);

            return [$user, true];
        } finally {
            $lock->release();
        }
    }

    public function isStaff(User $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::STAFF_ROLES);
    }

    private function createdByThisSession(Request $request, User $user): bool
    {
        $marker = $request->session()->get(self::CREATED_KEY);

        return is_array($marker)
            && (int) ($marker['id'] ?? 0) === (int) $user->id
            && $user->passwordless_since !== null
            && $user->email_verified_at === null
            && ((int) ($marker['at'] ?? 0)) > now()->subHours(2)->timestamp;
    }

    private function username(string $email): string
    {
        $base = Str::of((string) strtok($email, '@'))->slug('_')->value() ?: 'customer';
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.'_'.$suffix;
        }

        return $username;
    }
}
