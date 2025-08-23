<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Illuminate\Support\Facades\Log;

readonly class MarkProcessAsMultipleInstancesUseCase
{
    public function __construct(
        private ProcessRepositoryInterface $repository
    ) {}

    /**
     * Handles marking a process as having multiple instances.
     *
     * Updates the process to indicate it has multiple instances
     * based on the filing number.
     *
     * @param string $filingNumber The filing number of the process.
     * @return void
     */
    public function __invoke(string $filingNumber): void
    {
        // Marcar que tiene múltiples instancias
        $this->repository->updateProcessesByProcessNumber($filingNumber, ['has_multiple_instances' => true]);

        Log::channel('judicial_process_sync_job')->info(
            "Proceso con radicado {$filingNumber} marcado como múltiples instancias"
        );
    }
}
