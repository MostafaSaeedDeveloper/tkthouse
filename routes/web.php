<?php

use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('front.home');
Route::get('/about', [PagesController::class, 'about'])->name('front.about');
Route::get('/events', [PagesController::class, 'events'])->name('events.index');
Route::get('/events/{event?}', [PagesController::class, 'eventDetail'])->name('events.show');
Route::get('/contact', [PagesController::class, 'contact'])->name('front.contact');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
