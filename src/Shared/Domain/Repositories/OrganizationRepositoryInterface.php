<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    public function findSlug(string $slug): ?Organization;

    /**
     * Encuentra organizaciones por sus IDs
     */
    public function findByIds(array $ids): Collection;
}
