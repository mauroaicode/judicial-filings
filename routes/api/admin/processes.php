<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\Process\Controllers\AdminProcessActionController;
use Src\Application\Admin\Process\Controllers\AdminProcessConfigController;
use Src\Application\Admin\Process\Controllers\AdminProcessDetailController;
use Src\Application\Admin\Process\Controllers\AdminProcessImportHistoryController;
use Src\Application\Admin\Process\Controllers\AdminProcessInstancesController;
use Src\Application\Admin\Process\Controllers\AdminProcessStatusController;
use Src\Application\Admin\Process\Controllers\AdminProcessSubjectController;
use Src\Application\Admin\Process\Controllers\PrivateProcessExcelImportController;
use Src\Application\Admin\Process\Controllers\ProcessActuacionesImportController;
use Src\Application\Admin\Process\Controllers\ProcessController;
use Src\Application\Admin\Process\Controllers\ProcessImportController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('config/processes/roles', [AdminProcessConfigController::class, 'roles']);
    Route::post('processes/{processId}/organizations/{organizationId}/config/roles', [AdminProcessConfigController::class, 'update'])
        ->whereUuid(['processId', 'organizationId']);
    Route::patch('processes/{processId}/organizations/{organizationId}/status', [AdminProcessStatusController::class, 'update'])
        ->whereUuid(['processId', 'organizationId']);
    Route::put('processes/{processId}/subjects', [AdminProcessSubjectController::class, 'sync'])
        ->whereUuid('processId');
    Route::delete('processes/{processId}/subjects/{subjectId}', [AdminProcessSubjectController::class, 'destroy'])
        ->whereUuid(['processId', 'subjectId']);

    Route::get('processes', [ProcessController::class, 'index']);
    Route::get('processes/{id}', [AdminProcessDetailController::class, 'show'])->whereUuid('id');
    Route::get('processes/{id}/actions', [AdminProcessActionController::class, 'index'])->whereUuid('id');
    Route::get('processes/{id}/instances', [AdminProcessInstancesController::class, 'index'])->whereUuid('id');
    Route::get('processes/{id}/alert-keywords', [AdminProcessActionController::class, 'alertKeywords'])->whereUuid('id');
    Route::get('processes/{id}/alert-keyword-stats', [AdminProcessActionController::class, 'alertKeywordStats'])->whereUuid('id');
    Route::get('processes/import-history', [AdminProcessImportHistoryController::class, 'index']);
    Route::post('processes/import', [ProcessImportController::class, 'import']);
    Route::post('processes/private-import', [PrivateProcessExcelImportController::class, 'import']);
    Route::post('processes/actuaciones-import', [ProcessActuacionesImportController::class, 'import']);
});
