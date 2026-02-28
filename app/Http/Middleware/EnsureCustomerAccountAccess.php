<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccountAccess
{
    private const ADMIN_ROLES = [
        'admin',
        'event_manager',
        'ticket_manager',
        'support',
        'scanner',
        'super_admin',
        'super admin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ADMIN_ROLES)) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
