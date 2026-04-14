<?php

declare(strict_types=1);

namespace Src\Domain\Task\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Task\Models\Task;

/**
 * @extends Builder<Task>
 */
class TaskQueryBuilder extends Builder
{
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    public function whereAdmin(bool $isAdmin = true): self
    {
        return $this->where('is_admin', $isAdmin);
    }

    public function whereAppUser(): self
    {
        return $this->where('is_admin', false);
    }

    public function whereProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
    }

    public function orderedByCreatedAt(): self
    {
        return $this->latest();
    }
}
