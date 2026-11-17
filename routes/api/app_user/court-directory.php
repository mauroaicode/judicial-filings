<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\CourtDirectory\Controllers\AppUserCourtDirectoryController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('court-directory', [AppUserCourtDirectoryController::class, 'index']);
    Route::get('court-directory/by-desk-code/{deskCode}', [AppUserCourtDirectoryController::class, 'byDeskCode'])
        ->where('deskCode', '[0-9-]{12,40}');
});
