<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Notification\Controllers\NotificationDigestController;
use Src\Application\Admin\Notification\Controllers\NotificationDigestViewController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('notification-digests', [NotificationDigestController::class, 'index'])
        ->name('notification-digests.index');
    Route::get('notification-digests/{digest}/export-pdf', [NotificationDigestController::class, 'exportPdf'])
        ->whereUuid('digest')
        ->name('notification-digests.export-pdf');
    Route::get('notification-digests/{digest}/views', [NotificationDigestViewController::class, 'index'])
        ->whereUuid('digest')
        ->name('notification-digests.views.index');
});
