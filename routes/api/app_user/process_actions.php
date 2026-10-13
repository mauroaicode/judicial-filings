<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Process\Controllers\ProcessActionController;
use Src\Application\AppUser\Process\Controllers\ProcessActionDocumentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('processes/{processId}/actions', [ProcessActionController::class, 'index']);
    Route::get('processes/{processId}/action-documents', [ProcessActionDocumentController::class, 'index'])
        ->whereUuid('processId');
    Route::get('processes/{processId}/alert-keywords', [ProcessActionController::class, 'alertKeywords']);
    Route::get('processes/{processId}/alert-keyword-stats', [ProcessActionController::class, 'alertKeywordStats']);
    Route::get('media/{mediaId}/download', [ProcessActionDocumentController::class, 'download'])
        ->whereNumber('mediaId');
});
