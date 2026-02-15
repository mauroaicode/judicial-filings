<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\AlertKeyword\Controllers\AlertKeywordController;
use Src\Application\AppUser\OrganizationNotification\Controllers\OrganizationNotificationController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('alert-keywords', [AlertKeywordController::class, 'index']);
    Route::get('organization-notifications', [OrganizationNotificationController::class, 'index']);
    Route::patch('organization-notifications/mark-viewed', [OrganizationNotificationController::class, 'markViewed']);
    Route::patch('organization-notifications/mark-all-viewed', [OrganizationNotificationController::class, 'markAllViewed']);
});
