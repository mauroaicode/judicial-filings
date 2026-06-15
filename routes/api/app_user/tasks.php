<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Task\Controllers\TaskController;
use Src\Application\Shared\Task\Controllers\TaskStatusController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('tasks/statuses', TaskStatusController::class);
    Route::get('tasks/trash', [TaskController::class, 'trash']);
    Route::patch('tasks/{id}/complete', [TaskController::class, 'complete']);
    Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::post('tasks/{id}/restore', [TaskController::class, 'restore']);
    Route::delete('tasks/{id}/force', [TaskController::class, 'forceDestroy']);
    Route::apiResource('tasks', TaskController::class);
});
