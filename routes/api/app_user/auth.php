<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Auth\Controllers\AuthController;
use Src\Application\AppUser\Auth\Controllers\Google2FAController;

// Rutas públicas de autenticación
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Rutas protegidas de autenticación
Route::middleware(['auth:sanctum'])->group(function (): void {

    Route::post('verify-password', [AuthController::class, 'verifyPassword'])->middleware('throttle:5,1')->name('auth.verify-password');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1')->name('auth.verify-otp');

    Route::prefix('2fa')->name('2fa.')->group(function (): void {
        Route::get('generate', [Google2FAController::class, 'generateCode'])->name('generate');
        Route::post('verify', [Google2FAController::class, 'verifyGeneratedCode'])->name('verify');
        Route::patch('disable', [Google2FAController::class, 'disable'])->name('disable');
        Route::post('validate', [Google2FAController::class, 'validate2faAfterLogin'])->name('validate');
    });
});
