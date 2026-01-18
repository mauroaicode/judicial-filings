<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\Src\Domain\Process\Models\ProcessAction>
 */
class ProcessActionQueryBuilder extends Builder
{
    /**
     * Filter actions by process ID.
     *
     * @return $this
     */
    public function whereProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
    }

    /**
     * Filter actions by action registration ID.
     *
     * @return $this
     */
    public function whereActionRegistrationId(int $actionRegistrationId): self
    {
        return $this->where('action_registration_id', $actionRegistrationId);
    }

    /**
     * Filter actions by process and action registration ID.
     *
     * @return $this
     */
    public function whereProcessAndRegistrationId(string $processId, int $actionRegistrationId): self
    {
        return $this->whereProcess($processId)
            ->whereActionRegistrationId($actionRegistrationId);
    }

    /**
     * Order actions by action date (most recent first).
     *
     * @return $this
     */
    public function orderedByActionDate(): self
    {
        return $this->latest('action_date');
    }

    /**
     * Order actions by registration date (most recent first).
     *
     * @return $this
     */
    public function orderedByRegistrationDate(): self
    {
        return $this->latest('registration_date');
    }
}
