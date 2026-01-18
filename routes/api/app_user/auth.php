<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Auth\Controllers\AuthController;

Route::post('login', [AuthController::class, 'login']);
