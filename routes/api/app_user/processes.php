<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessController;
use Src\Application\AppUser\Process\Controllers\ProcessInstancesController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
    Route::post('processes', [ProcessController::class, 'store']);
    Route::get('processes/{id}', [ProcessController::class, 'show']);
    Route::get('processes/{id}/instances', [ProcessInstancesController::class, 'index']);
});
