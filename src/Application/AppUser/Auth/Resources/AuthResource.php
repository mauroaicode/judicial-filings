<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\AppUser\Models\AppUser;

class AuthResource extends Resource
{
    public function __construct(
        public string $token,
        public AppUserResource $user,
        public bool $requires_2fa = false,
        public bool $is_first_login = false,
    ) {}

    public static function fromModel(AppUser $appUser, string $token, bool $requires2fa = false, bool $isFirstLogin = false): self
    {
        return new self(
            token: $token,
            user: AppUserResource::fromModel($appUser),
            requires_2fa: $requires2fa,
            is_first_login: $isFirstLogin,
        );
    }
}
