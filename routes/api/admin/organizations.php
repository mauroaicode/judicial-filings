<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Organization\Controllers\OrganizationActiveStatusController;
use Src\Application\Admin\Organization\Controllers\OrganizationController;
use Src\Application\Admin\Organization\Controllers\OrganizationTypeController;
use Src\Application\Admin\Organization\Controllers\OrganizationUpdateNotificationStatusController;
use Src\Application\Shared\Process\Controllers\OrganizationProcessController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('organization-types', [OrganizationTypeController::class, 'index']);
    Route::get('organization-statuses', [OrganizationActiveStatusController::class, 'index']);
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::get('organizations/stats', [OrganizationController::class, 'stats']);
    Route::post('organizations', [OrganizationController::class, 'store']);
    Route::post('organizations/{organization}/notifications-status', OrganizationUpdateNotificationStatusController::class);
    Route::get('organizations/{organizationId}/processes', [OrganizationProcessController::class, 'index']);
});
