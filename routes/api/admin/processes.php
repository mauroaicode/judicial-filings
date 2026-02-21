<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Process\Controllers\ProcessController;
use Src\Application\Admin\Process\Controllers\ProcessImportController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('processes', [ProcessController::class, 'index']);
    Route::post('processes/import', [ProcessImportController::class, 'import']);
    Route::get('processes/import/batches/{id}', [ProcessImportController::class, 'showBatch']);
});
