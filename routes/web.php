<?php

use Illuminate\Support\Facades\Route;
use Src\Application\AppUser\Config\Controllers\ConfirmOrganizationNotificationChannelEmailController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('notification-channels/email/confirm/{channel}', ConfirmOrganizationNotificationChannelEmailController::class)
    ->name('notification-channels.email.confirm');
