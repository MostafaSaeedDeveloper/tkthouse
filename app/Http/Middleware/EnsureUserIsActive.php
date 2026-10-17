<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A deactivated account is signed out on its next request, whichever way it logged in (password, OTP, remember-me). */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account is disabled.'], 403)
                : redirect()->route('login')->withErrors(['username' => 'This account is disabled. Contact an administrator.']);
        }

        return $next($request);
    }
}
