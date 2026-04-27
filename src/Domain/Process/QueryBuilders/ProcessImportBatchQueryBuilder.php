<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Process\Models\ProcessImportBatch;

/**
 * @extends Builder<ProcessImportBatch>
 */
class ProcessImportBatchQueryBuilder extends Builder
{
    /**
     * Filter batches that belong to a specific organization.
     *
     * @return $this
     */
    public function whereOrganization(string $organizationId): self
    {
        return $this->where('organization_id', $organizationId);
    }

    /**
     * Filter batches by status.
     *
     * @return $this
     */
    public function whereStatus(string $status): self
    {
        return $this->where('status', $status);
    }

    /**
     * Order batches by created_at (most recent first).
     *
     * @return $this
     */
    public function orderedByCreatedAt(): self
    {
        return $this->latest('created_at');
    }
}
