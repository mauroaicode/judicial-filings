<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

readonly class ProcessActionService
{
    use ParseDateTrait;

    public function __construct(
        private JudicialBranchConsultService $judicialBranchConsultService
    ) {}

    /**
     * Handle the process of fetching and saving process actions.
     *
     * @param  Process  $process  The process to attach actions to.
     * @param  int  $processId  The API process ID.
     */
    public function handle(Process $process, int $processId): void
    {
        $actionsData = $this->fetchActionsFromJudicialBranch($processId);

        if ($actionsData === []) {
            return;
        }

        $this->saveActions($process, $actionsData);
    }

    /**
     * Fetch actions from the judicial branch API.
     *
     * @param  int  $processId  The API process ID.
     * @return array<int, array<string, mixed>>
     */
    private function fetchActionsFromJudicialBranch(int $processId): array
    {
        $actionsResponse = $this->judicialBranchConsultService->fetchActionByProcess($processId);

        if (! $actionsResponse->isSuccessful || empty($actionsResponse->data)) {
            return [];
        }

        return $actionsResponse->data;
    }

    /**
     * Save actions to the database.
     *
     * @param  Process  $process  The process to attach actions to.
     * @param  array<int, array<string, mixed>>  $actionsData  The action data from the API.
     */
    private function saveActions(Process $process, array $actionsData): void
    {
        foreach ($actionsData as $actionData) {
            $this->createOrUpdateAction($process, $actionData);
        }
    }

    /**
     * Create or update a process action record.
     *
     * @param  Process  $process  The process to attach the action to.
     * @param  array<string, mixed>  $actionData  The action data from the API.
     */
    private function createOrUpdateAction(Process $process, array $actionData): void
    {
        $actionRegistrationId = $actionData['idRegActuacion'] ?? null;

        if (! $actionRegistrationId) {
            return;
        }

        $existingAction = ProcessAction::query()
            ->whereProcessAndRegistrationId($process->id, $actionRegistrationId)
            ->first();

        if ($existingAction) {
            return;
        }

        $registrationDate = $this->parseDate($actionData['fechaRegistro'] ?? null)
            ?? now()->format('Y-m-d');

        $actionDate = $this->parseDate($actionData['fechaActuacion'] ?? null)
            ?? $registrationDate;

        ProcessAction::query()->create([
            'process_id' => $process->id,
            'action_registration_id' => $actionRegistrationId,
            'cons_action' => (int) ($actionData['consActuacion'] ?? 0),
            'action_date' => $actionDate,
            'action' => $actionData['actuacion'] ?? '',
            'annotation' => $actionData['anotacion'] ?? null,
            'start_date' => $this->parseDate($actionData['fechaInicial'] ?? null),
            'end_date' => $this->parseDate($actionData['fechaFinal'] ?? null),
            'registration_date' => $registrationDate,
        ]);
    }
}
