<?php

use App\Http\Controllers\Api\CheckinController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('/checkin/verify', [CheckinController::class, 'verify']);
    Route::post('/checkin/confirm', [CheckinController::class, 'confirm']);
});
