<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Config\Controllers\OrganizationNotificationChannelController;

Route::middleware('auth:sanctum')->prefix('config')->group(function (): void {
    Route::get('notification-channels/limits', [OrganizationNotificationChannelController::class, 'limits']);
    Route::get('notification-channels', [OrganizationNotificationChannelController::class, 'index']);
    Route::post('notification-channels', [OrganizationNotificationChannelController::class, 'store']);
    Route::put('notification-channels/{notificationChannel}', [OrganizationNotificationChannelController::class, 'update']);
    Route::delete('notification-channels/{notificationChannel}', [OrganizationNotificationChannelController::class, 'destroy']);
});
