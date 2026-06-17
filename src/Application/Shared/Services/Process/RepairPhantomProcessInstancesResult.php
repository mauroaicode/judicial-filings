<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

final readonly class RepairPhantomProcessInstancesResult
{
    public function __construct(
        public int $actionsRemoved,
        public int $notificationsRemoved,
        public int $phantomInstancesDetected,
    ) {}
}
