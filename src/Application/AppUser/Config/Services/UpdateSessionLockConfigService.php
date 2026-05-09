<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Services;

use Src\Application\AppUser\Config\Data\UpdateSessionLockConfigData;
use Src\Domain\AppUser\Models\AppUser;

class UpdateSessionLockConfigService
{
    public function handle(AppUser $appUser, UpdateSessionLockConfigData $data): void
    {
        $this->updateConfig($appUser, $data);
    }

    private function updateConfig(AppUser $appUser, UpdateSessionLockConfigData $data): void
    {
        AppUser::query()
            ->where('id', $appUser->id)
            ->update([
                'session_lock_enabled' => $data->session_lock_enabled,
                'session_lock_timeout' => $data->session_lock_timeout,
            ]);
    }
}
