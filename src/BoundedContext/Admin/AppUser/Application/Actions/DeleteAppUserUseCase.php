<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Actions;

use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class DeleteAppUserUseCase
{
    public function __construct(
        private AppUserRepositoryInterface $appUserRepository
    ) {}

    /**
     * Delete app customer
     */
    public function __invoke(string $id): void
    {
        $appUser = $this->appUserRepository->findById($id);

        if (!$appUser) {
            abort(Response::HTTP_NOT_FOUND, __('app_user.not_found'));
        }

        $deleted = $this->appUserRepository->delete($id);

        if (!$deleted) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, __('app_user.delete_failed'));
        }
    }
}
