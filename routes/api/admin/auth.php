<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Auth\Controllers\AuthController;

Route::post('login', [AuthController::class, 'login']);
