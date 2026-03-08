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
use App\Http\Controllers\Admin\PromoCodeController;
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
Route::post('/contact', [PagesController::class, 'submitContact'])->name('front.contact.submit');
Route::get('/terms-and-conditions', [PagesController::class, 'terms'])->name('front.terms');
Route::get('/privacy-policy', [PagesController::class, 'privacy'])->name('front.privacy');
Route::get('/cookie-policy', [PagesController::class, 'cookie'])->name('front.cookie');

Route::match(['GET','POST'], '/payments/paymob/callback', [CheckoutController::class, 'paymobCallback'])->name('front.paymob.callback');
Route::get('/payments/fawaterak/callback/{order}/{token}', [CheckoutController::class, 'fawaterakCallback'])->name('front.fawaterak.callback');

Route::middleware('guest')->group(function () {
    Route::get('/account/login', [CustomerAuthController::class, 'showLogin'])->name('front.customer.login');
    Route::post('/account/login', [CustomerAuthController::class, 'login'])->name('front.customer.login.store');
    Route::post('/account/register', [CustomerAuthController::class, 'requestRegisterOtp'])->name('front.customer.register.store');
    Route::post('/account/register/request-otp', [CustomerAuthController::class, 'requestRegisterOtp'])->name('front.customer.register.request-otp');
    Route::post('/account/register/verify-otp', [CustomerAuthController::class, 'verifyRegisterOtp'])->name('front.customer.register.verify-otp');
});

Route::get('/account/register', [CustomerAuthController::class, 'showRegister'])->name('front.customer.register');

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
    Route::get('/orders/{order}/payment/{token}/gateway', [CheckoutController::class, 'gatewayRedirect'])->name('front.orders.payment.gateway');
    Route::get('/tickets/{ticket:uuid}', [FrontTicketController::class, 'show'])->name('front.tickets.show');
    Route::get('/tickets/{ticket:uuid}/download', [FrontTicketController::class, 'download'])->name('front.tickets.download');
});

Auth::routes(['register' => false]);

Route::middleware(['auth', 'admin.panel'])->prefix('dashboard')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');

    Route::resource('users', UserController::class)->except('show')
        ->middlewareFor('index', 'permission:users.view')
        ->middlewareFor(['create', 'store'], 'permission:users.create')
        ->middlewareFor(['edit', 'update'], 'permission:users.update')
        ->middlewareFor('destroy', 'permission:users.delete');

    Route::resource('roles', RoleController::class)->except('show')
        ->middlewareFor('index', 'permission:roles.view')
        ->middlewareFor(['create', 'store'], 'permission:roles.create')
        ->middlewareFor(['edit', 'update'], 'permission:roles.update')
        ->middlewareFor('destroy', 'permission:roles.delete');

    Route::resource('permissions', PermissionController::class)->except('show')
        ->middlewareFor('index', 'permission:permissions.view')
        ->middlewareFor(['create', 'store'], 'permission:permissions.create')
        ->middlewareFor(['edit', 'update'], 'permission:permissions.update')
        ->middlewareFor('destroy', 'permission:permissions.delete');

    Route::resource('events', EventController::class)
        ->middlewareFor(['index', 'show'], 'permission:events.view')
        ->middlewareFor(['create', 'store'], 'permission:events.create')
        ->middlewareFor(['edit', 'update'], 'permission:events.update')
        ->middlewareFor('destroy', 'permission:events.delete');

    Route::resource('tickets', TicketController::class)
        ->middlewareFor(['index', 'show'], 'permission:tickets.view')
        ->middlewareFor(['create', 'store'], 'permission:tickets.create')
        ->middlewareFor(['edit', 'update'], 'permission:tickets.update')
        ->middlewareFor('destroy', 'permission:tickets.delete');

    Route::post('tickets/{ticket}/send-email', [TicketController::class, 'sendEmail'])->middleware('permission:tickets.update')->name('tickets.send-email');
    Route::get('tickets/{ticket}/send-whatsapp', [TicketController::class, 'sendWhatsapp'])->middleware('permission:tickets.update')->name('tickets.send-whatsapp');
    Route::get('tickets/{ticket}/download', [TicketController::class, 'download'])->middleware('permission:tickets.view')->name('tickets.download');
    Route::get('scanner', [TicketController::class, 'scanner'])->middleware('permission:scanner.access')->name('tickets.scanner');
    Route::post('scanner/lookup', [TicketController::class, 'scannerLookup'])->middleware('permission:scanner.access')->name('tickets.scanner.lookup');
    Route::post('scanner/{ticket}/status', [TicketController::class, 'scannerStatus'])->middleware('permission:scanner.access')->name('tickets.scanner.status');

    Route::get('orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('orders/deleted', [OrderController::class, 'deleted'])->middleware('permission:orders.deleted.view')->name('orders.deleted');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete')->name('orders.destroy');
    Route::post('orders/{order}/restore', [OrderController::class, 'restore'])->middleware('permission:orders.restore')->name('orders.restore');
    Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->middleware('permission:orders.update')->name('orders.edit');
    Route::put('orders/{order}', [OrderController::class, 'update'])->middleware('permission:orders.update')->name('orders.update');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:update_status')->name('orders.status.update');
    Route::post('orders/{order}/notes', [OrderController::class, 'storeNote'])->middleware('permission:orders.update')->name('orders.notes.store');
    Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->middleware('permission:orders.update')->name('orders.approve');
    Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->middleware('permission:orders.update')->name('orders.reject');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:attendees.view')->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:attendees.view')->name('customers.show');
    Route::get('affiliates', [AffiliateController::class, 'index'])->middleware('permission:attendees.view')->name('affiliates.index');
    Route::get('affiliates/create', [AffiliateController::class, 'create'])->middleware('permission:attendees.export')->name('affiliates.create');
    Route::post('affiliates', [AffiliateController::class, 'store'])->middleware('permission:attendees.export')->name('affiliates.store');
    Route::get('affiliates/{affiliate}', [AffiliateController::class, 'show'])->middleware('permission:attendees.view')->name('affiliates.show');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:activity-logs.view')->name('activity-logs.index');
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');

    Route::get('settings', [SystemSettingController::class, 'edit'])->middleware('permission:settings.view')->name('settings.edit');
    Route::put('settings', [SystemSettingController::class, 'update'])->middleware('permission:settings.update')->name('settings.update');

    Route::resource('payment-methods', PaymentMethodController::class)->except('show')
        ->middlewareFor('index', 'permission:payment-methods.view')
        ->middlewareFor(['create', 'store'], 'permission:payment-methods.create')
        ->middlewareFor(['edit', 'update'], 'permission:payment-methods.update')
        ->middlewareFor('destroy', 'permission:payment-methods.delete');
    Route::get('payment-methods/fawaterak/methods', [PaymentMethodController::class, 'fawaterakMethods'])->middleware('permission:payment-methods.view')->name('payment-methods.fawaterak-methods');

    Route::resource('promo-codes', PromoCodeController::class)->except('show')
        ->middlewareFor('index', 'permission:promo-codes.view')
        ->middlewareFor(['create', 'store'], 'permission:promo-codes.create')
        ->middlewareFor(['edit', 'update'], 'permission:promo-codes.update')
        ->middlewareFor('destroy', 'permission:promo-codes.delete');
});

Route::redirect('/admin', '/dashboard');
Route::redirect('/admin/dashboard', '/dashboard');
Route::redirect('/home', '/dashboard');
