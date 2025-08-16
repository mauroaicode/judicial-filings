<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface ProcessRepositoryInterface
{
    /**
     * Find all processes
     */
    public function findAll(): Collection;

    /**
     * Find a process by ID
     */
    public function findById(string $id): ?Process;

    /**
     * Find a process by process ID (API ID)
     */
    public function findByProcessId(int $processId): ?Process;

    /**
     * Find a process by process number
     */
    public function findByProcessNumber(string $processNumber): ?Process;

    /**
     * Exists a process with the given process number
     */
    public function existProcessNumber(string $processNumber): bool;

    /**
     * Find processes by organization
     */
    public function findByOrganization(string $organizationId): Collection;

    /**
     * Create a process
     */
    public function create(array $data): Process;

    /**
     * Update a process by process ID (API ID)
     */
    public function updateProcessByProcessId(int $processId, array $data): bool;

    /**
     * Update processes by process number
     */
    public function updateProcessesByProcessNumber(string $processNumber, array $data): int;

    /**
     * Create or update a process instance
     */
    public function createOrUpdateProcess(array $processData): Process;

    /**
     * Attach organization to process
     */
    public function attachOrganization(string $processId, string $organizationId, array $pivotData = []): void;

    /**
     * Get all unique process numbers from the system
     */
    public function getAllUniqueProcessNumbers(): SupportCollection;

    /**
     * Get all unique process numbers that have active organizations interested
     */
    public function getAllUniqueProcessNumbersWithActiveOrganizations(): SupportCollection;

    /**
     * Get organizations interested in a specific process number
     */
    public function getOrganizationsByProcessNumber(string $processNumber): Collection;

    /**
     * Assign organizations to a process
     */
    public function assignOrganizationsToProcess(string $processId, array $organizationIds): void;
}
