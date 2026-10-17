<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\StoreAffiliateReferral::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'orders.global' => \App\Http\Middleware\EnsureNotOrderScoped::class,
            'admin.panel' => \App\Http\Middleware\EnsureAdminPanelAccess::class,
            'customer.account' => \App\Http\Middleware\EnsureCustomerAccountAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Server-to-server gateway webhook: it has no session/CSRF token. It is authenticated by the Paymob HMAC
        // (mandatory in production) and, in any case, never trusted without a server-side transaction lookup.
        $middleware->validateCsrfTokens(except: ['payments/paymob/callback', 'payments/fawaterak/webhook_json']);

        $middleware->redirectGuestsTo(static function (Request $request): string {
            if ($request->is('dashboard') || $request->is('dashboard/*') || $request->is('admin') || $request->is('admin/*')) {
                return route('login');
            }

            return route('front.customer.login', ['redirect' => $request->fullUrl()]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
