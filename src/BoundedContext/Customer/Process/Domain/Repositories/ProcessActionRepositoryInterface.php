<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\ProcessAction;
use Illuminate\Database\Eloquent\Collection;

interface ProcessActionRepositoryInterface
{
    /**
     * Find all actions for a process
     */
    public function findByProcessId(string $processId): Collection;

    /**
     * Find an action by registration ID
     */
    public function findByRegistrationId(int $registrationId): ?ProcessAction;

    /**
     * Check if an action exists by registration ID
     */
    public function existsByRegistrationId(int $registrationId): bool;

    /**
     * Create a new action
     */
    public function create(array $data): ProcessAction;

    /**
     * Create multiple actions
     */
    public function createMany(array $actionsData): Collection;

    /**
     * Get actions that are not yet notified to organizations
     */
    public function getUnnotifiedActionsForProcess(string $processId): Collection;

    /**
     * Get the latest action for a process
     */
    public function getLatestActionForProcess(string $processId): ?ProcessAction;

    /**
     * Get actions created after a specific date
     */
    public function getActionsAfterDate(string $processId, string $date): Collection;
}
