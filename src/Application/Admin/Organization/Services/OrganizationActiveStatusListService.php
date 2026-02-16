<?php

declare(strict_types=1);

namespace Src\Application\Admin\Organization\Services;

use Src\Domain\Organization\QueryBuilders\OrganizationActiveStatusQueryBuilder;

readonly class OrganizationActiveStatusListService
{
    public function __construct(
        private OrganizationActiveStatusQueryBuilder $organizationActiveStatusQueryBuilder
    ) {}

    /**
     * Get the list of organization active/inactive statuses for frontend (dropdowns/filters).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function handle(): array
    {
        return $this->organizationActiveStatusQueryBuilder->getList();
    }
}
