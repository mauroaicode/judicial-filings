<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Src\Application\Admin\Process\Resources\AdminProcessInstanceResource;
use Src\Domain\Process\Models\Process;

readonly class AdminProcessInstancesService
{
    /**
     * Return all instances (same process_number) across the system (admin view),
     * ordered by last_activity_date DESC.
     *
     * @return Collection<int, array>
     */
    public function handle(string $processId): Collection
    {
        $process = Process::query()
            ->where('id', $processId)
            ->first();

        if (! $process instanceof Process) {
            return collect();
        }

        return Process::query()
            ->where('process_number', $process->process_number)
            ->withCount('actions')
            ->orderedByLastActivityDate()
            ->get()
            ->map(fn (Process $p): array => AdminProcessInstanceResource::fromModel($p)->toArray())
            ->values();
    }
}
