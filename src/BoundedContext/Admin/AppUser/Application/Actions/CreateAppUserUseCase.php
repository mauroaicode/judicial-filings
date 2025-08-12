<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Actions;

use Core\BoundedContext\Admin\AppUser\Application\Data\CreateAppUserData;
use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\AppUser;
use Illuminate\Support\Facades\Hash;

readonly class CreateAppUserUseCase
{
    public function __construct(
        private AppUserRepositoryInterface $appUserRepository
    ) {}

    /**
     * Create app customer
     */
    public function __invoke(CreateAppUserData $data): AppUser
    {
        return $this->appUserRepository->create([
            'name' => $data->name,
            'last_name' => $data->last_name,
            'slug' => $data->slug,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
    }
}
