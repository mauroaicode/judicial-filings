<?php

declare(strict_types=1);

namespace Src\Domain\Organization\QueryBuilders;

use Src\Domain\Organization\Enums\OrganizationActiveStatus;

/**
 * Builds the list of organization active/inactive statuses for API (dropdowns/filters).
 * Does not query a database; provides the enum list in a consistent QueryBuilder-style API.
 */
class OrganizationActiveStatusQueryBuilder
{
    /**
     * Get the list of active/inactive statuses (value + label) for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getList(): array
    {
        return OrganizationActiveStatus::toArray();
    }
}
