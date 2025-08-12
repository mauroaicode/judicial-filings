<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Actions;

use Core\BoundedContext\Admin\AppUser\Application\Data\UpdateAppUserData;
use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\AppUser;
use Symfony\Component\HttpFoundation\Response;

readonly class UpdateAppUserUseCase
{
    public function __construct(
        private AppUserRepositoryInterface $appUserRepository
    ) {}

    /**
     * Update app customer
     */
    public function __invoke(string $id, UpdateAppUserData $data): AppUser
    {
        $appUser = $this->appUserRepository->findById($id);

        if (!$appUser) {
            abort(Response::HTTP_NOT_FOUND, __('app_user.not_found'));
        }

        $updated = $this->appUserRepository->update($id, [
            'name' => $data->name,
            'last_name' => $data->last_name,
            'slug' => $data->slug,
            'email' => $data->email,
        ]);

        if (!$updated) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, __('app_user.update_failed'));
        }

        return $this->appUserRepository->findById($id);
    }
}
