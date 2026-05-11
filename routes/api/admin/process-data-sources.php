<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Process\Controllers\ProcessDataSourceController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('process-data-sources', [ProcessDataSourceController::class, 'index']);
});
