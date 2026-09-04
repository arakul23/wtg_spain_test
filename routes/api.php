<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReservationController;

Route::get('/imports/{import}', [ImportController::class, 'show']);
Route::get('/properties', [PropertyController::class, 'index']);
Route::post('/imports', [ImportController::class, 'store']);
Route::post('/offers/{offer}/reservations', [ReservationController::class, 'store']);