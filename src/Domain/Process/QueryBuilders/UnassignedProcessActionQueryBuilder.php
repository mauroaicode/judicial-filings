<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Process\Models\UnassignedProcessAction;

/**
 * @extends Builder<UnassignedProcessAction>
 */
class UnassignedProcessActionQueryBuilder extends Builder
{
    public function whereProcessNumber(string $processNumber): self
    {
        return $this->where('process_number', $processNumber);
    }

    public function whereUnassigned(): self
    {
        return $this->whereNull('assigned_at');
    }

    public function whereDedupeHash(string $hash): self
    {
        return $this->where('dedupe_hash', $hash);
    }

    public function orderedByRegistrationDate(): self
    {
        return $this->orderBy('registration_date')->orderBy('created_at');
    }
}
