<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Actions;

use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\AppUser;
use Symfony\Component\HttpFoundation\Response;

readonly class FindAppUserUseCase
{
    public function __construct(
        private AppUserRepositoryInterface $appUserRepository
    ) {}

    /**
     * Find app customer by ID
     */
    public function __invoke(string $id): AppUser
    {
        $appUser = $this->appUserRepository->findById($id);

        if (!$appUser) {
            abort(Response::HTTP_NOT_FOUND, __('app_user.not_found'));
        }

        return $appUser;
    }
}
