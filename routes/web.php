<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/front.php';
Auth::routes();
require __DIR__.'/admin.php';
require __DIR__.'/customer.php';

Route::redirect('/home', '/admin');
