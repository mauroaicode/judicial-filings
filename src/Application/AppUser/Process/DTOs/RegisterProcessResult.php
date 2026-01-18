<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\DTOs;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\Process;

readonly class RegisterProcessResult
{
    /**
     * @param  Collection<int, Process>  $processes  The registered processes.
     * @param  bool  $hasMultipleInstances  Whether the radicado has multiple instances.
     * @param  int  $totalProcesses  Total number of processes found in the API.
     * @param  int  $registeredCount  Number of processes successfully registered.
     * @param  int  $privateCount  Number of private processes that were not registered.
     */
    public function __construct(
        public Collection $processes,
        public bool $hasMultipleInstances,
        public int $totalProcesses,
        public int $registeredCount,
        public int $privateCount,
    ) {}

    /**
     * Get the first process (for backward compatibility).
     */
    public function getFirstProcess(): ?Process
    {
        return $this->processes->first();
    }
}
