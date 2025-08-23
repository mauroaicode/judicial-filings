<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Illuminate\Support\Facades\Log;

readonly class AssignOrganizationsToProcessUseCase
{
    public function __construct(
        private ProcessRepositoryInterface $repository
    ) {}

    /**
     * Handles the assignment of organizations to a process.
     *
     * Assigns the given organizations to the specified process,
     * ensuring proper relationship management.
     *
     * @param string $processId The ID of the process.
     * @param array $organizationIds Array of organization IDs to assign.
     * @return void
     */
    public function __invoke(string $processId, array $organizationIds): void
    {
        if (empty($organizationIds)) {
            Log::channel('judicial_process_sync_job')->warning("No se proporcionaron organizaciones para asignar al proceso {$processId}");
            return;
        }

        // Asignar las organizaciones al proceso
        $this->repository->assignOrganizationsToProcess($processId, $organizationIds);

        Log::channel('judicial_process_sync_job')->info(
            "Proceso {$processId} asignado a " . count($organizationIds) . " organizaciones: " . implode(', ', $organizationIds)
        );
    }
}
