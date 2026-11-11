<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Auth\Controllers\AuthController;
use Src\Application\Admin\Auth\Controllers\Google2FAController;

Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->prefix('2fa')->name('2fa.')->group(function (): void {
    Route::get('generate', [Google2FAController::class, 'generateCode'])->name('generate');
    Route::post('verify', [Google2FAController::class, 'verifyGeneratedCode'])->name('verify');
    Route::patch('disable', [Google2FAController::class, 'disable'])->name('disable');
    Route::post('validate', [Google2FAController::class, 'validate2faAfterLogin'])->name('validate');
});
