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
        $middleware->redirectGuestsTo(static function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('login');
            }

            return route('front.customer.login', ['redirect' => $request->fullUrl()]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
