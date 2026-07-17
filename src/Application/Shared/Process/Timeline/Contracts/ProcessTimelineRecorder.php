<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Contracts;

use Src\Application\Shared\Process\Timeline\DTOs\RecordProcessTimelineEventData;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessTimelineEvent;

interface ProcessTimelineRecorder
{
    public function handle(Process $process, RecordProcessTimelineEventData $data): ProcessTimelineEvent;
}
