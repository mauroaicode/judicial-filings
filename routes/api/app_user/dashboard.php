<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Dashboard\Controllers\DashboardController;
use Src\Application\AppUser\Dashboard\Controllers\DashboardStatsController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard/stats', [DashboardStatsController::class, 'index']);
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
});
