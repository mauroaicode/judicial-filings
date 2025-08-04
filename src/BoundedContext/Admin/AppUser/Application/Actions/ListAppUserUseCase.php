<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Actions;

use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

readonly class ListAppUserUseCase
{
    public function __construct(
        private AppUserRepositoryInterface $appUserRepository
    ) {}

    /**
     * List all app customers
     */
    public function __invoke(): Collection
    {
        return $this->appUserRepository->findAll();
    }
}
