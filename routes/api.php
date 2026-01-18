<?php

use Illuminate\Support\Facades\Route;

Route::prefix('app-user')->middleware('api')->group(function () {
    $appUserRoutesPath = __DIR__.'/api/app_user';

    if (is_dir($appUserRoutesPath)) {
        $files = glob($appUserRoutesPath.'/*.php');

        foreach ($files as $file) {
            require $file;
        }
    }
});
