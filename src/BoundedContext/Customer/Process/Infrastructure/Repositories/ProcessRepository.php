<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

readonly class ProcessRepository implements ProcessRepositoryInterface
{
    public function __construct(
        private Process $process
    ) {}

    public function findAll(): Collection
    {
        return $this->process::query()->get();
    }

    public function findById(string $id): ?Process
    {
        return $this->process::query()->find($id);
    }

    public function findByProcessId(int $processId): ?Process
    {
        return $this->process::query()
            ->where('process_id', $processId)
            ->first();
    }

    public function findByProcessNumber(string $processNumber): ?Process
    {
        return $this->process::query()
            ->where('process_number', $processNumber)
            ->first();
    }

    public function findByOrganization(string $organizationId): Collection
    {
        return $this->process::query()
            ->whereHas('organizations', function ($query) use ($organizationId) {
                $query->where('organizations.id', $organizationId)
                      ->where('organization_processes.is_active', true);
            })
            ->get();
    }


    public function create(array $data): Process
    {
        return $this->process::query()->create($data);
    }


    public function attachOrganization(string $processId, string $organizationId, array $pivotData = []): void
    {
        $process = $this->findById($processId);

        if ($process) {
            $process->organizations()->attach($organizationId, $pivotData);
        }
    }

    public function existProcessNumber(string $processNumber): bool
    {
        return $this->process::query()
            ->where('process_number', $processNumber)
            ->exists();
    }

    /**
     * Get all unique process numbers from the system
     */
    public function getAllUniqueProcessNumbers(): SupportCollection
    {
        return $this->process::query()
            ->select('process_number')
            ->distinct()
            ->pluck('process_number');
    }

    /**
     * Get all unique process numbers that have active organizations interested
     */
    public function getAllUniqueProcessNumbersWithActiveOrganizations(): SupportCollection
    {
        return $this->process::query()
            ->whereHas('organizations', function ($query) {
                $query->where('organization_processes.is_active', true);
            })
            ->distinct()
            ->pluck('process_number');
    }

    /**
     * Get organizations interested in a specific process number
     */
    public function getOrganizationsByProcessNumber(string $processNumber): Collection
    {
        $process = $this->process::query()
            ->where('process_number', $processNumber)
            ->first();

        if (!$process) {
            return collect();
        }

        return $process->organizations()
            ->where('organization_processes.is_active', true)
            ->get();
    }

    /**
     * Assign organizations to a process
     */
    public function assignOrganizationsToProcess(string $processId, array $organizationIds): void
    {
        $process = $this->process::query()->find($processId);

        if (!$process) {
            return;
        }

        $existingOrganizations = $process->organizations()->pluck('organizations.id')->toArray();

        $newOrganizationIds = array_diff($organizationIds, $existingOrganizations);

        if (!empty($newOrganizationIds)) {
            $pivotData = [];
            foreach ($newOrganizationIds as $orgId) {
                $pivotData[$orgId] = [
                    'is_active' => true,
                    'interest_date' => now(),
                ];
            }

            $process->organizations()->attach($pivotData);
        }
    }

    /**
     * Update a process by process ID (API ID)
     */
    public function updateProcessByProcessId(int $processId, array $data): bool
    {
        return $this->process::query()
            ->where('process_id', $processId)
            ->update($data) > 0;
    }

    /**
     * Update processes by process number
     */
    public function updateProcessesByProcessNumber(string $processNumber, array $data): int
    {
        return $this->process::query()
            ->where('process_number', $processNumber)
            ->update($data);
    }

    /**
     * Create or update a process instance
     */
    public function createOrUpdateProcess(array $processData): Process
    {
        $processId = $processData['process_id'];

        $existingProcess = $this->findByProcessId($processId);

        if ($existingProcess) {
            $existingProcess->update($processData);
            return $existingProcess->fresh();
        }

        return $this->create($processData);
    }
}
