<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Domain\Process\Models\Process;

readonly class AdminProcessDetailService
{
    /**
     * Loads a process with subjects and organizations for admin detail views.
     */
    public function handle(string $processId): ?Process
    {
        return Process::query()
            ->where('id', $processId)
            ->withSubjects()
            ->with('organizations')
            ->first();
    }
}
