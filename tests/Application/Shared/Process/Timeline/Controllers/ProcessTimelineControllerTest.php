<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Timeline\Controllers\ProcessTimelineController;

it('has an application controller for process timeline requests', function (): void {
    expect(class_exists(ProcessTimelineController::class))->toBeTrue();
});
