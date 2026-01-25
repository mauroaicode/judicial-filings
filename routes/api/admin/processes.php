<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Process\Controllers\ProcessController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
});
