<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Organization\Controllers\UpdateOrganizationAiAccessController;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('organizations/{organizationId}/ai-access', UpdateOrganizationAiAccessController::class);
});
