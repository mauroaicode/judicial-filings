<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiForbiddenOrRateLimitException;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Process\ProcessSyncService;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\Process\Models\Process;

readonly class RegisterProcessService
{
    use ParseDateTrait;

    public function __construct(
        private JudicialBranchConsultService $judicialBranchConsultService,
        private ProcessSyncService $processSyncService
    ) {}

    /**
     * Registers a radicado for an organization.
     *
     * Fast path: if any instance of the radicado already exists in DB (registered by another org),
     * all instances are attached directly without any API call.
     * Full path: if the radicado is not in DB, the Rama Judicial API is consulted.
     *
     * @param  string  $processNumber  23-digit radicado number
     * @param  string  $organizationId  Organization UUID
     * @param  string  $proxySeed  Seed for proxy pool selection (e.g. processNumber:attempt)
     *
     * @throws \Throwable
     */
    public function handle(string $processNumber, string $organizationId, string $proxySeed = ''): RegisterProcessResult
    {
        if ($proxySeed !== '') {
            $this->judicialBranchConsultService->withSeed($proxySeed);
        }

        $this->validateProcessNotAlreadyRegistered($processNumber, $organizationId);

        $existingProcesses = Process::query()->whereProcessNumber($processNumber)->get();

        if ($existingProcesses->isNotEmpty()) {
            return $this->attachExistingProcesses($existingProcesses, $organizationId);
        }

        return $this->registerFromApi($processNumber, $organizationId);
    }

    /**
     * Attaches all existing DB instances of a radicado to the organization (no API calls).
     *
     * Called when the radicado is already registered globally (by another organization).
     * The daily sync guarantees all instances are up to date in the DB.
     *
     * @param  Collection<int, Process>  $processes  All DB instances of the radicado
     * @param  string  $organizationId  Organization UUID
     *
     * @throws \Throwable
     */
    private function attachExistingProcesses(Collection $processes, string $organizationId): RegisterProcessResult
    {
        return DB::transaction(function () use ($processes, $organizationId): RegisterProcessResult {
            /** @var Collection<int, Process> $attached */
            $attached = collect();
            $privateCount = 0;

            foreach ($processes as $process) {
                if ($process->is_private) {
                    $privateCount++;

                    continue;
                }

                $this->attachProcessToOrganization($process, $organizationId);
                $attached->push($process);
            }

            if ($attached->isEmpty()) {
                abort(422, __('process.all_instances_are_private'));
            }

            return new RegisterProcessResult(
                processes: $attached,
                hasMultipleInstances: $attached->count() > 1,
                totalProcesses: $processes->count(),
                registeredCount: $attached->count(),
                privateCount: $privateCount,
            );
        });
    }

    /**
     * Consults the Rama Judicial API to register a new radicado and sync its actions/subjects.
     *
     * Called only when no instance of the radicado exists in DB.
     * For each API instance: if the process_id is already in DB (race condition), it attaches;
     * otherwise it creates the record and syncs actuaciones and sujetos procesales.
     *
     * @param  string  $processNumber  23-digit radicado number
     * @param  string  $organizationId  Organization UUID
     *
     * @throws \Throwable
     */
    private function registerFromApi(string $processNumber, string $organizationId): RegisterProcessResult
    {
        $processesData = $this->validateAndGetProcessesFromJudicialBranch($processNumber);

        $hasMultipleInstances = count($processesData) > 1;
        $totalProcesses = count($processesData);

        /** @var Collection<int, Process> $registeredProcesses */
        $registeredProcesses = collect();
        $privateCount = 0;

        return DB::transaction(function () use ($processNumber, $organizationId, $processesData, $hasMultipleInstances, $totalProcesses, &$registeredProcesses, &$privateCount): RegisterProcessResult {
            foreach ($processesData as $processData) {
                $isPrivate = $processData['esPrivado'] ?? false;

                if ($isPrivate) {
                    $privateCount++;

                    continue;
                }

                $processId = $processData['idProceso'] ?? null;

                if (! $processId) {
                    continue;
                }

                $globalProcess = Process::query()->whereProcessId($processId)->first();

                if ($globalProcess) {
                    if ($globalProcess->is_private) {
                        $privateCount++;

                        continue;
                    }

                    if ($hasMultipleInstances && ! $globalProcess->has_multiple_instances) {
                        $globalProcess->update(['has_multiple_instances' => true]);
                    }

                    $this->attachProcessToOrganization($globalProcess, $organizationId);
                    $registeredProcesses->push($globalProcess);

                    continue;
                }

                $detailData = $this->validateAndGetProcessDetails($processId);
                $fechaUltimaActuacion = $processData['fechaUltimaActuacion'] ?? null;

                $process = $this->createProcess($processNumber, $processId, $detailData, $hasMultipleInstances, $fechaUltimaActuacion);
                $this->attachProcessToOrganization($process, $organizationId);
                $this->processSyncService->handle($process);

                $registeredProcesses->push($process);
            }

            if ($registeredProcesses->isEmpty()) {
                if ($totalProcesses === 1 && $privateCount === 1) {
                    abort(422, __('process.is_private'));
                }

                abort(422, __('process.all_instances_are_private'));
            }

            return new RegisterProcessResult(
                processes: $registeredProcesses,
                hasMultipleInstances: $hasMultipleInstances,
                totalProcesses: $totalProcesses,
                registeredCount: $registeredProcesses->count(),
                privateCount: $privateCount,
            );
        });
    }

    /**
     * Create a new process record.
     *
     * @param  string|null  $fechaUltimaActuacion  From the list-by-number API (procesos[].fechaUltimaActuacion), not from detail.
     */
    private function createProcess(string $processNumber, int $processId, array $detailData, bool $hasMultipleInstances = false, ?string $fechaUltimaActuacion = null): Process
    {
        $processData = [
            'process_id' => $processId,
            'process_number' => $processNumber,
            'court' => $detailData['despacho'] ?? '',
            'speaker' => $detailData['ponente'] ?? null,
            'department' => $detailData['departamento'] ?? '',
            'process_type' => $detailData['tipoProceso'] ?? '',
            'process_class' => $detailData['claseProceso'] ?? '',
            'subclass_process' => $detailData['subclaseProceso'] ?? null,
            'litigants' => $detailData['sujetosProcesales'] ?? null,
            'process_date' => $this->parseDate($detailData['fechaProceso'] ?? null) ?? now()->toDateString(),
            'last_activity_date' => $this->parseDate($fechaUltimaActuacion),
            'location' => $detailData['ubicacion'] ?? null,
            'filing_content' => $detailData['contenidoRadicacion'] ?? null,
            'is_private' => $detailData['esPrivado'] ?? false,
            'has_multiple_instances' => $hasMultipleInstances,
            'last_api_update' => now(),
        ];

        return Process::query()->create($processData);
    }

    /**
     * Validate that the process is not already registered for the organization.
     *
     * @param  string  $processNumber  The process number to validate.
     * @param  string  $organizationId  The organization ID.
     */
    private function validateProcessNotAlreadyRegistered(string $processNumber, string $organizationId): void
    {
        $existingProcess = Process::query()
            ->whereProcessNumber($processNumber)
            ->whereHas('organizations', function (Builder $query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId);
            })
            ->first();

        if ($existingProcess) {
            abort(422, __('process.already_registered'));
        }
    }

    /**
     * Validate that the process exists in the judicial branch and get all processes.
     *
     * @param  string  $processNumber  The process number to validate.
     * @return array<int, array<string, mixed>> The processes data from the API.
     */
    private function validateAndGetProcessesFromJudicialBranch(string $processNumber): array
    {
        try {
            $response = $this->judicialBranchConsultService->fetchProcesses($processNumber);
        } catch (ApiEmptyProcessesException) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        if (! $response->isSuccessful || empty($response->data)) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        return $response->data;
    }

    /**
     * Validate and get detailed process information from the judicial branch.
     *
     * @param  int  $processId  The API process ID.
     * @return array<string, mixed> The detailed process data.
     *
     * @throws ApiForbiddenOrRateLimitException
     */
    private function validateAndGetProcessDetails(int $processId): array
    {
        $detailResponse = $this->judicialBranchConsultService->fetchDetailProcess($processId);

        if (! $detailResponse->isSuccessful || empty($detailResponse->data)) {
            abort(404, __('process.not_found_in_judicial_branch'));
        }

        return $detailResponse->data;
    }

    /**
     * Attach a process to an organization.
     *
     * @param  Process  $process  The process to attach.
     * @param  string  $organizationId  The organization ID.
     */
    private function attachProcessToOrganization(Process $process, string $organizationId): void
    {
        $process->organizations()->syncWithoutDetaching([
            $organizationId => [
                'interest_date' => now()->toDateString(),
                'is_active' => true,
            ],
        ]);
    }
}
