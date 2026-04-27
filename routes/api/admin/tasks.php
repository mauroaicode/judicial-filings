<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Task\Controllers\TaskController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::apiResource('tasks', TaskController::class);
});
