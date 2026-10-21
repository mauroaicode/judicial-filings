<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence;

use Illuminate\Contracts\Auth\Authenticatable;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\User\Models\User;

class AuthorizeAppUserPresenceChannel
{
    public function __construct(
        private readonly AppUserActivityRecorder $recorder,
    ) {}

    /**
     * @return array<string, string>|false
     */
    public function __invoke(Authenticatable $user): array|false
    {
        if ($user instanceof AppUser) {
            $this->recorder->record($user->id, request()->ip(), false);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
            ];
        }

        if ($user instanceof User && $user->hasRole('admin', 'admin')) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'guard' => 'admin',
            ];
        }

        return false;
    }
}
