<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Optional "set a password" for accounts that were created by Express Checkout.
 * Allowed only for such passwordless accounts, and only shortly after the e-mail code was entered in this
 * session — the screen itself therefore cannot be used to take over a normal account.
 */
class AccountPasswordController extends Controller
{
    private const FRESH_SECONDS = 900;

    public function show(Request $request)
    {
        $this->authorizeSetup($request);

        return view('front.account.set-password');
    }

    public function store(Request $request)
    {
        $this->authorizeSetup($request);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'passwordless_since' => null])->save(); // 'hashed' cast

        $request->session()->forget('auth.otp_verified_at');

        return redirect()->route('front.account.profile')->with('success', 'Your password is set. You can still sign in with an email code any time.');
    }

    private function authorizeSetup(Request $request): void
    {
        $user = $request->user();
        $verifiedAt = (int) $request->session()->get('auth.otp_verified_at', 0);

        abort_unless(
            $user && $user->passwordless_since !== null && $verifiedAt >= now()->subSeconds(self::FRESH_SECONDS)->timestamp,
            403
        );
    }
}
