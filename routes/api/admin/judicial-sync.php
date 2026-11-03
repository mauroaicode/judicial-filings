<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\JudicialSync\Controllers\AdminJudicialSyncController;
use Src\Application\Admin\JudicialSync\Controllers\AdminJudicialSyncHistoryController;
use Src\Application\Admin\JudicialSync\Controllers\AdminJudicialSyncLiveStatusController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::post('judicial-sync', [AdminJudicialSyncController::class, 'sync']);
    Route::get('judicial-sync/status', AdminJudicialSyncLiveStatusController::class);
    Route::get('judicial-sync/runs', [AdminJudicialSyncHistoryController::class, 'index']);
});
