<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontendMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.frontend_maintenance') || (bool) \App\Support\SystemSettings::get('frontend_maintenance', false) || config('app.app_maintenance')) {
            return response(file_get_contents(public_path('index_maintenance.html')), 503)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
