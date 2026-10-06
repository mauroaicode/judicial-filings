<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Organization\Controllers\OrganizationActiveStatusController;
use Src\Application\Admin\Organization\Controllers\OrganizationController;
use Src\Application\Admin\Organization\Controllers\OrganizationSettingsController;
use Src\Application\Admin\Organization\Controllers\OrganizationTypeController;
use Src\Application\Admin\Organization\Controllers\OrganizationUpdateNotificationStatusController;
use Src\Application\Admin\Process\Controllers\OrganizationProcessExportController;
use Src\Application\Shared\Process\Controllers\OrganizationProcessController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('organization-types', [OrganizationTypeController::class, 'index']);
    Route::get('organization-statuses', [OrganizationActiveStatusController::class, 'index']);
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::get('organizations/stats', [OrganizationController::class, 'stats']);
    Route::post('organizations', [OrganizationController::class, 'store']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'show']);
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update']);
    Route::post('organizations/{organization}/notifications-status', OrganizationUpdateNotificationStatusController::class);
    Route::get('organizations/{organizationId}/processes', [OrganizationProcessController::class, 'index']);
    Route::post('organizations/{organization}/processes/export', [OrganizationProcessExportController::class, 'store'])
        ->name('organizations.processes.export');
    Route::get('organizations/{organization}/processes/exports', [OrganizationProcessExportController::class, 'index'])
        ->name('organizations.processes.exports.index');
    Route::get('organizations/{organization}/processes/exports/{processExport}', [OrganizationProcessExportController::class, 'show'])
        ->name('organizations.processes.exports.show');
    Route::post('organizations/{organization}/processes/exports/{processExport}/rerun', [OrganizationProcessExportController::class, 'rerun'])
        ->name('organizations.processes.exports.rerun');
    Route::get('organizations/{organization}/processes/exports/{processExport}/download', [OrganizationProcessExportController::class, 'download'])
        ->name('organizations.processes.exports.download');
});
