<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Shared\Process\Controllers\ProcessDataSourceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('process-data-sources', [ProcessDataSourceController::class, 'index']);
});
