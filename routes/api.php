<?php

use Illuminate\Support\Facades\Route;

Route::prefix('app-user')->name('app-user.')->middleware(['api', 'app_user.organization_active'])->group(function () {
    $appUserRoutesPath = __DIR__.'/api/app_user';

    if (is_dir($appUserRoutesPath)) {
        $files = glob($appUserRoutesPath.'/*.php');

        foreach ($files as $file) {
            require $file;
        }
    }
});

Route::prefix('admin')->name('admin.')->middleware('api')->group(function () {
    $adminRoutesPath = __DIR__.'/api/admin';

    if (is_dir($adminRoutesPath)) {
        $files = glob($adminRoutesPath.'/*.php');

        foreach ($files as $file) {
            require $file;
        }
    }
});
