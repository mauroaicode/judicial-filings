<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Auth\Controllers\AuthController;

// Rutas públicas de autenticación
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Rutas protegidas de autenticación
Route::middleware(['auth:sanctum'])->group(function (): void {

    Route::post('verify-password', [AuthController::class, 'verifyPassword'])->middleware('throttle:5,1')->name('auth.verify-password');
});
