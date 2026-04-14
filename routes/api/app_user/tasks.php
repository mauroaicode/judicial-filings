<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Task\Controllers\TaskController;

Route::apiResource('tasks', TaskController::class);
