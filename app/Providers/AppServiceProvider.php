<?php

namespace App\Providers;

use App\Listeners\CaptureMailMessageId;
use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Order::observe(OrderObserver::class);
        Event::listen(MessageSent::class, CaptureMailMessageId::class);
    }
}
