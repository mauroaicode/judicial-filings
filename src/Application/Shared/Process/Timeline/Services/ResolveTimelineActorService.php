<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Src\Domain\AppUser\Models\AppUser;

class ResolveTimelineActorService
{
    /**
     * @return array{type: string|null, id: string|null}
     */
    public function handle(): array
    {
        /** @var Authenticatable|null $actor */
        $actor = auth()->user();

        if (! $actor instanceof Authenticatable) {
            return ['type' => null, 'id' => null];
        }

        return [
            'type' => $actor instanceof AppUser ? 'app_user' : 'admin',
            'id' => (string) $actor->getAuthIdentifier(),
        ];
    }
}
