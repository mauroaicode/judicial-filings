<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Database\Eloquent\Collection;

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
}
