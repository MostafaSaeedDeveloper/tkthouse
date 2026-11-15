<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use App\Services\Checkout\CheckoutIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "View your tickets" for people who are not signed in: the buyer (also one who paid as a guest) and holders a ticket was
 * bought for. They enter their e-mail and the 6-digit code we send (the existing /auth/otp/verify step), and land on their ticket.
 * A holder with no account gets a passwordless one the moment their address is a real ticket holder's; nothing is granted
 * until the code proves they can read that mailbox.
 */
class TicketAccessController extends Controller
{
    public function show(Request $request)
    {
        $target = $this->safeTarget((string) $request->query('redirect'));

        if (Auth::check()) {
            return redirect($target);
        }

        return view('front.tickets.access', ['target' => $target]);
    }

    public function send(Request $request, EmailOtpService $otp, CheckoutIdentity $identity)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = EmailOtpService::normalize($data['email']);

        $existing = EmailOtpService::findUser($email);
        if ($existing && $identity->isStaff($existing)) {
            return response()->json(['ok' => true, 'message' => 'If this email can receive a code, it is on its way.']); // staff sign in with their password
        }

        if (! $existing) {
            $this->createHolderAccount($email);
        }

        $status = $otp->send($email, $request->session()->getId(), $request->ip());

        if ($status === 'limited') {
            return response()->json(['ok' => false, 'message' => 'Too many codes requested. Please wait a while and try again.'], 429);
        }

        // The same answer whether or not the address has an account or a ticket.
        return response()->json(['ok' => true, 'message' => $status === 'cooldown'
            ? 'A code was just sent. Please wait a few seconds before asking for another.'
            : 'If this email can receive a code, it is on its way.']);
    }

    /** A passwordless customer account for an address that holds at least one real ticket and has no account yet. */
    private function createHolderAccount(string $email): void
    {
        $ticket = IssuedTicket::query()
            ->whereRaw('LOWER(holder_email) = ?', [$email])
            ->whereHas('order', fn ($o) => $o->whereIn('status', IssuedTicket::PORTAL_ORDER_STATUSES))
            ->latest('id')->first();

        if (! $ticket) {
            return;
        }

        $base = Str::of((string) strtok($email, '@'))->slug('_')->value() ?: 'customer';
        $username = $base;
        for ($i = 2; User::query()->where('username', $username)->exists(); $i++) {
            $username = $base.'_'.$i;
        }

        try {
            User::create([
                'name' => trim((string) $ticket->holder_name) ?: $base,
                'username' => $username,
                'email' => $email,
                'phone' => $ticket->holder_phone ?: null,
                'password' => Str::random(64), // nobody knows it: this account signs in with e-mail codes
                'passwordless_since' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Created a moment ago by another request.
        }
    }

    /** Only places on this site are valid return targets. */
    private function safeTarget(string $redirect): string
    {
        if ($redirect !== '' && ((str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) || str_starts_with($redirect, url('/').'/'))) {
            return $redirect;
        }

        return route('front.account.tickets');
    }
}
