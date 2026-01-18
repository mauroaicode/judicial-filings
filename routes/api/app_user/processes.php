<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
    Route::post('processes', [ProcessController::class, 'store']);
});
