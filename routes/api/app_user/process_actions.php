<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessActionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes/{processId}/actions', [ProcessActionController::class, 'index']);
});
