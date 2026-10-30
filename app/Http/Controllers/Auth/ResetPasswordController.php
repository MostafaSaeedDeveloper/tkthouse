<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    use ResetsPasswords;

    protected function redirectTo(): string
    {
        return route('front.customer.login');
    }

    /** The reset link went to the mailbox, so a successful reset proves it: guest-checkout orders become visible. */
    protected function resetPassword($user, $password)
    {
        $user->forceFill(['password' => $password, 'passwordless_since' => null, 'remember_token' => \Illuminate\Support\Str::random(60)]);
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }
        $user->save();
        \App\Services\Auth\GuestOrderAccess::releaseFor($user);

        event(new \Illuminate\Auth\Events\PasswordReset($user));
        $this->guard()->login($user);
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('front.auth.passwords.reset', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }
}
