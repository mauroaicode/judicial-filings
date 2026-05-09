<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Config\Controllers\UpdateSessionLockConfigController;

Route::middleware('auth:sanctum')->prefix('config')->name('config.')->group(function () {
    Route::put('session-lock', UpdateSessionLockConfigController::class);
});
