<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Notification\Controllers\AdminNotificationController;

Route::middleware(['auth:sanctum', 'admin.role'])->prefix('notifications')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index']);
    Route::get('/unread-count', [AdminNotificationController::class, 'unreadCount']);
    Route::post('/{id}/read', [AdminNotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [AdminNotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [AdminNotificationController::class, 'destroy']);
});
