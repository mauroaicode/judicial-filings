<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Presence\Controllers\AppUserPresenceController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function (): void {
    Route::get('presence/today', [AppUserPresenceController::class, 'today']);
    Route::get('presence/summary', [AppUserPresenceController::class, 'summary']);
    Route::get('presence/online', [AppUserPresenceController::class, 'online']);
});
