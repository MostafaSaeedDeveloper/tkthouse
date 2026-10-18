<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-pending-payments')->everyMinute();
Schedule::command('orders:reconcile-payments')->everyTwoMinutes()->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\Payments\Routing\CheckoutRoutingService::class)->prune())->name('checkout:prune-routings')->hourly();
Schedule::command('payments:reconcile-refunds')->everyTenMinutes()->withoutOverlapping();
Schedule::command('payments:sync')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('orders:retry-ticket-issuance')->everyFiveMinutes()->withoutOverlapping();

// Bell notifications are a convenience, not a record: read ones go after 30 days, anything after 90.
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('notifications')
    ->where(fn ($q) => $q->where(fn ($r) => $r->whereNotNull('read_at')->where('read_at', '<', now()->subDays(30)))->orWhere('created_at', '<', now()->subDays(90)))
    ->delete())->name('notifications:prune')->daily();
