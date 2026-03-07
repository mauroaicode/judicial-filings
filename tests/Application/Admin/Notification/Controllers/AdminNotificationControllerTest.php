<?php

declare(strict_types=1);

use Src\Application\Admin\Notification\Controllers\AdminNotificationController;

it('ensures admin notification controller exists', function (): void {
    expect(class_exists(AdminNotificationController::class))->toBeTrue();
});
