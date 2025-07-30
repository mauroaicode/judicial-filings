<?php

use Core\BoundedContext\Admin\Auth\Infrastructure\Controllers\{
    LoginController,
    LogoutController
};
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class);
Route::post('logout', LogoutController::class)->middleware('auth:sanctum');
