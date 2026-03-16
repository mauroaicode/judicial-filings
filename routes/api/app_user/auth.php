<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Auth\Controllers\AuthController;

Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
