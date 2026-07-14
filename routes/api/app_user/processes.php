<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessConfigController;
use Src\Application\AppUser\Process\Controllers\ProcessController;
use Src\Application\AppUser\Process\Controllers\ProcessImportHistoryController;
use Src\Application\AppUser\Process\Controllers\ProcessInstancesController;
use Src\Application\Shared\Task\Controllers\ProcessTaskController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
    Route::post('processes', [ProcessController::class, 'store']);

    // Specific named routes must come before wildcard {id} routes
    Route::get('processes/import-history', [ProcessImportHistoryController::class, 'index']);

    Route::get('processes/{id}', [ProcessController::class, 'show']);
    Route::patch('processes/{id}/status', [ProcessController::class, 'toggleStatus']);
    Route::get('processes/{id}/instances', [ProcessInstancesController::class, 'index']);
    Route::get('processes/{processId}/tasks', [ProcessTaskController::class, 'index'])->whereUuid('processId');

    // Configuration routes (Semaphore, Lawyer Role)
    Route::get('config/processes/roles', [ProcessConfigController::class, 'roles']);
    Route::post('processes/{id}/config/roles', [ProcessConfigController::class, 'update']);
    Route::patch('processes/bulk-config/roles', [ProcessConfigController::class, 'bulkUpdate']);
});
