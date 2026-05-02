<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Dashboard\Controllers\AdminDashboardStatsController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('dashboard/stats', [AdminDashboardStatsController::class, 'index']);
});
