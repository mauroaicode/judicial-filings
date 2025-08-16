<?php

namespace Core\Shared\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;

interface OrganizationRepositoryInterface
{
    /**
     * Find organization by slug
     *
     * @param string $slug
     * @return Organization|null
     */
    public function findSlug(string $slug): ?Organization;
}
