<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Organization\Controllers\UpdateOrganizationAiAccessController;
use Src\Application\AppUser\Organization\Controllers\GetOrganizationAiStatusController;
use Src\Application\Shared\Process\Controllers\OrganizationProcessController;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('organizations/{organizationId}/ai-access', UpdateOrganizationAiAccessController::class);
    Route::get('organizations/{organizationId}/ai-status', GetOrganizationAiStatusController::class);
    Route::get('organizations/{organizationId}/processes', [OrganizationProcessController::class, 'index']);
});
