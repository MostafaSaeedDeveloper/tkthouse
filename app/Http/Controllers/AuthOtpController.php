<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Auth\EmailOtpService;
use App\Services\Checkout\CheckoutIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless e-mail codes used inside checkout (small modal), on the confirmation page, and on emailed payment links.
 * Signing in with a code is only possible for customer accounts; staff accounts keep their password sign-in.
 */
class AuthOtpController extends Controller
{
    public function send(Request $request, EmailOtpService $otp)
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'order' => ['nullable', 'string', 'max:40'],
            'token' => ['nullable', 'string', 'max:100'],
        ]);

        $email = (string) ($data['email'] ?? '');

        // Emailed payment links: the address comes from the order, never from the browser.
        if (! empty($data['order'])) {
            $order = Order::query()->where('order_number', $data['order'])->with('user')->first();
            abort_unless($order && $order->payment_link_token && hash_equals($order->payment_link_token, (string) ($data['token'] ?? '')) && $order->user, 404);
            $email = $order->user->email;
        }

        if ($email === '') {
            throw ValidationException::withMessages(['email' => 'Please enter your email address.']);
        }

        $status = $otp->send($email, $request->session()->getId(), $request->ip());

        if ($status === 'limited') {
            return response()->json(['ok' => false, 'message' => 'Too many codes requested. Please wait a while and try again.'], 429);
        }
        if ($status === 'cooldown') {
            return response()->json(['ok' => true, 'message' => 'A code was just sent. Please wait a few seconds before asking for another.']);
        }

        // Identical answer whether or not the address has an account.
        return response()->json(['ok' => true, 'message' => 'If this email can receive a code, it is on its way.']);
    }

    public function verify(Request $request, EmailOtpService $otp, CheckoutIdentity $identity)
    {
        $data = $request->validate([
            'email' => ['required_without:order', 'nullable', 'email', 'max:255'],
            'code' => ['required', 'string', 'max:10'],
            'order' => ['nullable', 'string', 'max:40'],
            'token' => ['nullable', 'string', 'max:100'],
        ]);

        $email = (string) ($data['email'] ?? '');
        if (! empty($data['order'])) {
            $order = Order::query()->where('order_number', $data['order'])->with('user')->first();
            abort_unless($order && $order->payment_link_token && hash_equals($order->payment_link_token, (string) ($data['token'] ?? '')) && $order->user, 404);
            $email = $order->user->email;
        }

        $user = $otp->verify($email, trim($data['code']), $request->session()->getId());

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'That code is not valid or has expired. Check the latest email, or ask for a new code.'], 422);
        }

        if ($identity->isStaff($user)) {
            return response()->json(['ok' => false, 'message' => 'Staff accounts sign in with their password.'], 403);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate(); // keeps the checkout selection, rotates the session id
        $request->session()->put('auth.otp_verified_at', now()->timestamp);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Verified.',
            'name' => $user->name,
            'csrf' => csrf_token(),
            'passwordless' => $user->passwordless_since !== null,
        ]);
    }
}
