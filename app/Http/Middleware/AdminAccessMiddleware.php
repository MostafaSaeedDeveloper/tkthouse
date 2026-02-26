<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'admin', 'organizer', 'cashier', 'support']), 403);

        return $next($request);
    }
}
