<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessActionRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

readonly class ProcessActionRepository implements ProcessActionRepositoryInterface
{
    public function __construct(
        private ProcessAction $processAction
    ) {}

    /**
     * Find all actions for a process
     */
    public function findByProcessId(string $processId): Collection
    {
        return $this->processAction::query()
            ->where('process_id', $processId)
            ->orderBy('action_date', 'desc')
            ->get();
    }

    /**
     * Find an action by registration ID
     */
    public function findByRegistrationId(int $registrationId): ?ProcessAction
    {
        return $this->processAction::query()
            ->where('action_registration_id', $registrationId)
            ->first();
    }

    /**
     * Check if an action exists by registration ID
     */
    public function existsByRegistrationId(int $registrationId): bool
    {
        return $this->processAction::query()
            ->where('action_registration_id', $registrationId)
            ->exists();
    }

    /**
     * Create a new action
     */
    public function create(array $data): ProcessAction
    {
        return $this->processAction::query()->create($data);
    }

    /**
     * Create multiple actions
     */
    public function createMany(array $actionsData): Collection
    {
        $createdActions = collect();

        foreach ($actionsData as $actionData) {
            try {
                $action = $this->create($actionData);
                $createdActions->push($action);
            } catch (\Exception $e) {
                Log::channel('judicial_process_chunk_job')->error('Error creando actuación: ' . $e->getMessage(), [
                    'action_data' => $actionData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $createdActions;
    }

    /**
     * Get actions that are not yet notified to organizations
     */
    public function getUnnotifiedActionsForProcess(string $processId): Collection
    {
        return $this->processAction::query()
            ->where('process_id', $processId)
            ->whereDoesntHave('notifiedOrganizations')
            ->orderBy('action_date', 'desc')
            ->get();
    }

    /**
     * Get the latest action for a process
     */
    public function getLatestActionForProcess(string $processId): ?ProcessAction
    {
        return $this->processAction::query()
            ->where('process_id', $processId)
            ->orderBy('action_date', 'desc')
            ->first();
    }

    /**
     * Get actions created after a specific date
     */
    public function getActionsAfterDate(string $processId, string $date): Collection
    {
        return $this->processAction::query()
            ->where('process_id', $processId)
            ->where('action_date', '>', $date)
            ->orderBy('action_date', 'desc')
            ->get();
    }
}
