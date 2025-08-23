<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Repositories;

use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Support\Collection;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    /**
     * Find organization by name
     *
     * @param string $name
     * @return Organization|null
     */
    public function findName(string $name): ?Organization
    {
        return Organization::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * Find organization by slug
     *
     * @param string $slug
     * @return Organization|null
     */
    public function findSlug(string $slug): ?Organization
    {
        return Organization::query()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Encuentra organizaciones por sus IDs
     */
    public function findByIds(array $ids): Collection
    {
        return Organization::query()->whereIn('id', $ids)->get();
    }
}
