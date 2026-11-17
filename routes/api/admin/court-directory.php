<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\CourtDirectory\Controllers\AdminCourtDirectoryReadController;
use Src\Application\Admin\CourtDirectory\Controllers\AdminCourtDirectorySyncController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('court-directory', [AdminCourtDirectoryReadController::class, 'index']);
    Route::get('court-directory/by-desk-code/{deskCode}', [AdminCourtDirectoryReadController::class, 'byDeskCode'])
        ->where('deskCode', '[0-9-]{12,40}');
    Route::post('court-directory/sync', [AdminCourtDirectorySyncController::class, 'sync']);
    Route::get('court-directory/runs', [AdminCourtDirectorySyncController::class, 'index']);
});
