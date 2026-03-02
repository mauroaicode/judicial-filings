<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Notification\Controllers\AppUserNotificationController;

Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
    Route::get('/', [AppUserNotificationController::class, 'index']);
    Route::get('/unread-count', [AppUserNotificationController::class, 'unreadCount']);
    Route::post('/{id}/read', [AppUserNotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [AppUserNotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [AppUserNotificationController::class, 'destroy']);
});
