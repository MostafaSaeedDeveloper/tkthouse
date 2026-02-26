<?php

use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ReferralController;
use App\Http\Controllers\Customer\TicketController;
use App\Http\Controllers\Customer\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('account')->name('customer.')->middleware(['auth', 'customer.access'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('orders', OrderController::class)->only('index', 'show');
    Route::resource('tickets', TicketController::class)->only('index', 'show');
    Route::get('tickets/{ticket}/download', [TicketController::class, 'download'])->name('tickets.download');
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::get('referral', [ReferralController::class, 'index'])->name('referral.index');
});
