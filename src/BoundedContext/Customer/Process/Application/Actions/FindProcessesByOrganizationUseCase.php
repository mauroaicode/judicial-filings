<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

readonly class FindProcessesByOrganizationUseCase
{
    public function __construct(
        private ProcessRepositoryInterface $processRepository
    ) {}

    /**
     * Find all processes that a specific organization is following
     */
    public function execute(string $organizationId): Collection
    {
        return $this->processRepository->findByOrganization($organizationId);
    }
} 