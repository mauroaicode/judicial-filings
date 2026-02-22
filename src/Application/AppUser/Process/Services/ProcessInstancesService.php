<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Collection;
use Src\Application\AppUser\Process\Resources\ProcessInstanceResource;
use Src\Domain\Process\Models\Process;

readonly class ProcessInstancesService
{
    /**
     * Return all instances (same process_number) that belong to the organization,
     * ordered by last_activity_date DESC.
     *
     * @return Collection<int, mixed>
     */
    public function handle(string $processId, string $organizationId): Collection
    {
        $process = $this->findBaseProcess($processId, $organizationId);

        if (! $process instanceof Process) {
            return collect();
        }

        return $this->findAllInstances($process->process_number, $organizationId);
    }

    private function findBaseProcess(string $processId, string $organizationId): ?Process
    {
        return Process::query()
            ->where('id', $processId)
            ->whereOrganization($organizationId)
            ->first();
    }

    /**
     * @return Collection<int, mixed>
     */
    private function findAllInstances(string $processNumber, string $organizationId): Collection
    {
        return Process::query()
            ->where('process_number', $processNumber)
            ->whereOrganization($organizationId)
            ->withCount('actions')
            ->with(['organizations' => function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            }])
            ->orderedByLastActivityDate()
            ->get()
            ->map(fn (Process $process): array => ProcessInstanceResource::fromModel($process, $organizationId)->toArray())
            ->values();
    }
}
