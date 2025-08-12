<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Database\Eloquent\Collection;

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
     * Find processes by organization
     */
    public function findByOrganization(string $organizationId): Collection;


    /**
     * Create a process
     */
    public function create(array $data): Process;


    /**
     * Attach organization to process
     */
    public function attachOrganization(string $processId, string $organizationId, array $pivotData = []): void;

}
