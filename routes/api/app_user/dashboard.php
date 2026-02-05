<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Dashboard\Controllers\DashboardStatsController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard/stats', [DashboardStatsController::class, 'index']);
});
