<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Controllers;

use Src\Application\AppUser\Auth\Resources\AppUserResource;
use Src\Application\AppUser\Config\Data\UpdateSessionLockConfigData;
use Src\Application\AppUser\Config\Services\UpdateSessionLockConfigService;
use Src\Domain\AppUser\Models\AppUser;

class UpdateSessionLockConfigController
{
    public function __invoke(
        UpdateSessionLockConfigData $data,
        UpdateSessionLockConfigService $service
    ): AppUserResource {
        /** @var AppUser $user */
        $user = auth()->user();

        $service->handle($user, $data);

        return AppUserResource::fromModel($user->refresh());
    }
}
