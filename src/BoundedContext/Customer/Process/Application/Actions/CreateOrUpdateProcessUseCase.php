<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Illuminate\Support\Facades\Log;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;


readonly class CreateOrUpdateProcessUseCase
{
    public function __construct(
        private ProcessRepositoryInterface $repository
    ) {}

    /**
     * Handles the creation or update of a process entity.
     *
     * Creates or updates a Process domain object with the given data,
     * and delegates persistence to the repository.
     *
     * @param array $processData The data required to create or update the process.
     * @return Process The created or updated process.
     */
    public function __invoke(array $processData): Process
    {
        $mappedData = [
            'process_id' => $processData['process_id'],
            'process_number' => $processData['process_number'],
            'court' => $processData['despacho'],
            'department' => $processData['departamento'],
            'process_type' => $processData['tipoProceso'],
            'process_class' => $processData['claseProceso'],
            'subclass_process' => $processData['subclaseProceso'],
            'litigants' => $processData['sujetosProcesales'],
            'process_date' => $processData['fechaProceso'],
            'last_activity_date' => $processData['fechaUltimaActuacion'],
            'location' => $processData['ubicacion'],
            'filing_content' => $processData['contenidoRadicacion'],
            'is_private' => $processData['esPrivado'],
            'last_api_update' => $processData['last_api_update'],
        ];

        $process = $this->repository->createOrUpdateProcess($mappedData);

        if ($process->wasRecentlyCreated) {
            Log::channel('judicial_process_sync_job')->info("Nuevo proceso creado: {$processData['process_id']}");
        } else {
            Log::channel('judicial_process_sync_job')->info("Proceso actualizado: {$processData['process_id']}");
        }

        return $process;
    }
}
