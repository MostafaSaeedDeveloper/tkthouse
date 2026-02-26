<?php

use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin.access'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', fn () => redirect()->route('dashboard'))->name('root');
        Route::resource('events', EventController::class)->except('destroy', 'show');
        Route::resource('orders', OrderController::class)->only('index', 'show');
        Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid');
        Route::resource('tickets', TicketController::class)->only('index', 'show');
        Route::post('tickets/{ticket}/resend', [TicketController::class, 'resend'])->name('tickets.resend');
        Route::get('tickets/{ticket}/download', [TicketController::class, 'download'])->name('tickets.download');
        Route::get('checkin/scanner', [CheckinController::class, 'scanner'])->name('checkin.scanner');
        Route::get('reports/events', [ReportController::class, 'events'])->name('reports.events');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
