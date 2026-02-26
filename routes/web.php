<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('front.home');
Route::get('/about', [PagesController::class, 'about'])->name('front.about');
Route::get('/events', [PagesController::class, 'events'])->name('front.events');
Route::get('/events/tkt-house-techno-night', [PagesController::class, 'eventShow'])->name('front.events.show');
Route::get('/contact', [PagesController::class, 'contact'])->name('front.contact');
Route::get('/checkout', [PagesController::class, 'checkout'])->name('front.checkout');

Auth::routes();

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::view('/dashboard', 'admin.index')->name('dashboard');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('permissions', PermissionController::class)->except('show');
    Route::resource('events', EventController::class);
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

Route::redirect('/dashboard', '/admin/dashboard');
Route::redirect('/home', '/admin/dashboard');
