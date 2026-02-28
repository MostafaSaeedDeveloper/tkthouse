<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\FrontTicketController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('front.home');
Route::get('/about', [PagesController::class, 'about'])->name('front.about');
Route::get('/events', [PagesController::class, 'events'])->name('front.events');
Route::get('/events/{event:slug}', [PagesController::class, 'eventShow'])->name('front.events.show');
Route::get('/contact', [PagesController::class, 'contact'])->name('front.contact');

Route::match(['GET','POST'], '/payments/paymob/callback', [CheckoutController::class, 'paymobCallback'])->name('front.paymob.callback');

Route::middleware('guest')->group(function () {
    Route::get('/account/login', [CustomerAuthController::class, 'showLogin'])->name('front.customer.login');
    Route::post('/account/login', [CustomerAuthController::class, 'login'])->name('front.customer.login.store');
    Route::get('/account/register', [CustomerAuthController::class, 'showRegister'])->name('front.customer.register');
    Route::post('/account/register', [CustomerAuthController::class, 'register'])->name('front.customer.register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/account/logout', [CustomerAuthController::class, 'logout'])->name('front.customer.logout');
    Route::redirect('/account/dashboard', '/account/profile')->name('front.account.dashboard');
    Route::get('/account/profile', [CustomerDashboardController::class, 'profile'])->name('front.account.profile');
    Route::put('/account/profile', [CustomerDashboardController::class, 'updateProfile'])->name('front.account.profile.update');
    Route::get('/account/orders', [CustomerDashboardController::class, 'orders'])->name('front.account.orders');
    Route::get('/account/tickets', [CustomerDashboardController::class, 'tickets'])->name('front.account.tickets');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('front.checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('front.checkout.store');
    Route::get('/checkout/thank-you', [CheckoutController::class, 'thankYou'])->name('front.checkout.thank-you');
    Route::get('/orders/{order}/payment/{token}', [CheckoutController::class, 'paymentPage'])->name('front.orders.payment');
    Route::post('/orders/{order}/payment/{token}', [CheckoutController::class, 'confirmPayment'])->name('front.orders.payment.confirm');
    Route::get('/orders/{order}/payment/{token}/paymob', [CheckoutController::class, 'paymobRedirect'])->name('front.orders.payment.paymob');
    Route::get('/tickets/{ticket:uuid}', [FrontTicketController::class, 'show'])->name('front.tickets.show');
    Route::get('/tickets/{ticket:uuid}/download', [FrontTicketController::class, 'download'])->name('front.tickets.download');
});

Auth::routes(['register' => false]);

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('permissions', PermissionController::class)->except('show');
    Route::resource('events', EventController::class);
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/send-email', [TicketController::class, 'sendEmail'])->name('tickets.send-email');
    Route::get('tickets/{ticket}/send-whatsapp', [TicketController::class, 'sendWhatsapp'])->name('tickets.send-whatsapp');
    Route::get('tickets/{ticket}/download', [TicketController::class, 'download'])->name('tickets.download');
    Route::get('scanner', [TicketController::class, 'scanner'])->name('tickets.scanner');
    Route::post('scanner/lookup', [TicketController::class, 'scannerLookup'])->name('tickets.scanner.lookup');
    Route::post('scanner/{ticket}/status', [TicketController::class, 'scannerStatus'])->name('tickets.scanner.status');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/notes', [OrderController::class, 'storeNote'])->name('orders.notes.store');
    Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');
    Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::get('affiliates/create', [AffiliateController::class, 'create'])->name('affiliates.create');
    Route::post('affiliates', [AffiliateController::class, 'store'])->name('affiliates.store');
    Route::get('affiliates/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::resource('payment-methods', PaymentMethodController::class)->except('show');
});

Route::redirect('/dashboard', '/admin/dashboard');
Route::redirect('/home', '/admin/dashboard');
