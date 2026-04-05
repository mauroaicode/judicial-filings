<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessConfigController;
use Src\Application\AppUser\Process\Controllers\ProcessController;
use Src\Application\AppUser\Process\Controllers\ProcessInstancesController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
    Route::post('processes', [ProcessController::class, 'store']);
    Route::get('processes/{id}', [ProcessController::class, 'show']);
    Route::patch('processes/{id}/status', [ProcessController::class, 'toggleStatus']);
    Route::get('processes/{id}/instances', [ProcessInstancesController::class, 'index']);

    // Configuration routes (Semaphore, Lawyer Role)
    Route::get('config/processes/roles', [ProcessConfigController::class, 'roles']);
    Route::post('processes/{id}/config/roles', [ProcessConfigController::class, 'update']);
    Route::patch('processes/bulk-config/roles', [ProcessConfigController::class, 'bulkUpdate']);
});
