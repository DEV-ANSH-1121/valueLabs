<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SlotController;
use Illuminate\Support\Facades\Route;

Route::get('/slots', [SlotController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
