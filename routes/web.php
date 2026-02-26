<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('front.home');
Route::get('/about', [PagesController::class, 'about'])->name('front.about');
Route::get('/events', [PagesController::class, 'events'])->name('front.events');
Route::get('/events/{event}', [PagesController::class, 'eventShow'])->name('front.events.show');
Route::get('/contact', [PagesController::class, 'contact'])->name('front.contact');

Route::middleware('guest')->group(function () {
    Route::get('/account/login', [CustomerAuthController::class, 'showLogin'])->name('front.customer.login');
    Route::post('/account/login', [CustomerAuthController::class, 'login'])->name('front.customer.login.store');
    Route::get('/account/register', [CustomerAuthController::class, 'showRegister'])->name('front.customer.register');
    Route::post('/account/register', [CustomerAuthController::class, 'register'])->name('front.customer.register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/account/logout', [CustomerAuthController::class, 'logout'])->name('front.customer.logout');
    Route::get('/account/dashboard', [CustomerDashboardController::class, 'index'])->name('front.account.dashboard');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('front.checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('front.checkout.store');
    Route::get('/checkout/thank-you', [CheckoutController::class, 'thankYou'])->name('front.checkout.thank-you');
    Route::get('/orders/{order}/payment/{token}', [CheckoutController::class, 'paymentPage'])->name('front.orders.payment');
    Route::post('/orders/{order}/payment/{token}', [CheckoutController::class, 'confirmPayment'])->name('front.orders.payment.confirm');
});

Auth::routes(['register' => false]);

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::view('/dashboard', 'admin.index')->name('dashboard');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('permissions', PermissionController::class)->except('show');
    Route::resource('events', EventController::class);
    Route::resource('tickets', TicketController::class)->except('show');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

Route::redirect('/dashboard', '/admin/dashboard');
Route::redirect('/home', '/admin/dashboard');
