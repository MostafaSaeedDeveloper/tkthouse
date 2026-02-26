<?php

use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'home'])->name('front.home');
Route::get('/events', [EventController::class, 'index'])->name('front.events.index');
Route::get('/events/{slug}', [EventController::class, 'show'])->name('front.events.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('front.checkout.store');
Route::get('/orders/{order_no}/success', [CheckoutController::class, 'success'])->name('front.orders.success');

Route::view('/about', 'front.about')->name('front.about');
Route::view('/contact', 'front.contact')->name('front.contact');

Route::get('/events-legacy', fn () => redirect()->route('front.events.index'))->name('front.events');
