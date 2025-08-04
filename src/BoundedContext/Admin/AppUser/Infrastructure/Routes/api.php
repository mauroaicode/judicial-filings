<?php

use Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers\{
    ListAppUsersController,
    CreateAppUserController,
    ShowAppUserController,
    UpdateAppUserController,
    DeleteAppUserController
};
use Illuminate\Support\Facades\Route;

Route::prefix('app-user')->group(function () {
    Route::get('/', ListAppUsersController::class);
    Route::post('/create', CreateAppUserController::class);
    Route::get('/{id}', ShowAppUserController::class);
    Route::put('/{id}/update', UpdateAppUserController::class);
    Route::delete('/{id}', DeleteAppUserController::class);
});
