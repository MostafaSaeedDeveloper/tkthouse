<?php

namespace App\Http\Middleware;

use App\Orders\OrderAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Global (cross-order) screens are closed to users restricted by Order Assignment. No-op while the feature is off. */
class EnsureNotOrderScoped
{
    public function handle(Request $request, Closure $next): Response
    {
        if (OrderAccess::scoped($request->user())) {
            abort(403, 'This screen shows data for all orders and is not available for your access level.');
        }

        return $next($request);
    }
}
