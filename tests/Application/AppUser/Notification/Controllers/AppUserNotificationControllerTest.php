<?php

declare(strict_types=1);

use Src\Application\AppUser\Notification\Controllers\AppUserNotificationController;

it('ensures app user notification controller exists', function (): void {
    expect(class_exists(AppUserNotificationController::class))->toBeTrue();
});
