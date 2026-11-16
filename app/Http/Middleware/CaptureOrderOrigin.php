<?php

namespace App\Http\Middleware;

use App\Support\OrderOrigin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Remembers where the visitor came from (utm_source / referrer) so the order they place can record its origin. */
class CaptureOrderOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            OrderOrigin::capture($request);
        }

        return $next($request);
    }
}
