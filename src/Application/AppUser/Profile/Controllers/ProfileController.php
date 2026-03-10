<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Profile\Controllers;

use Src\Application\AppUser\Auth\Resources\AppUserResource;
use Src\Application\AppUser\Profile\Data\UpdateProfileData;
use Src\Application\AppUser\Profile\Services\UpdateProfileService;
use Src\Domain\AppUser\Models\AppUser;

class ProfileController
{
    public function show(): AppUserResource
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        return AppUserResource::fromModel($appUser);
    }

    public function update(UpdateProfileData $data, UpdateProfileService $service): AppUserResource
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $updatedUser = $service->handle($appUser, $data);

        return AppUserResource::fromModel($updatedUser);
    }
}
