<?php

use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'home'])->name('home.front');
Route::get('/about', [PagesController::class, 'about'])->name('about');
Route::get('/contact', [PagesController::class, 'contact'])->name('contact');
Route::get('/events', [PagesController::class, 'events'])->name('events.index');
Route::get('/events/{event?}', [PagesController::class, 'event'])->name('events.show');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
