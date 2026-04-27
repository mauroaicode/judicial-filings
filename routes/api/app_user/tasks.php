<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Task\Controllers\TaskController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tasks', TaskController::class);
});
