<?php

use Illuminate\Support\Facades\Route;
use Src\Application\Admin\DigestPackage\Controllers\DigestPackageController;

Route::middleware(['auth:sanctum', 'admin.role'])->group(function () {
    Route::get('digest-packages/preview', [DigestPackageController::class, 'preview']);
    Route::post('digest-packages/send', [DigestPackageController::class, 'send']);
});
