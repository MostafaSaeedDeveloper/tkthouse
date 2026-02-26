<?php

use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('front.home');
Route::get('/about', [PagesController::class, 'about'])->name('front.about');
Route::get('/events', [PagesController::class, 'events'])->name('front.events');
Route::get('/events/tkt-house-techno-night', [PagesController::class, 'eventShow'])->name('front.events.show');
Route::get('/contact', [PagesController::class, 'contact'])->name('front.contact');
Route::get('/checkout', [PagesController::class, 'checkout'])->name('front.checkout');

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'admin.index')->name('dashboard');
    Route::redirect('/home', '/dashboard');
});
