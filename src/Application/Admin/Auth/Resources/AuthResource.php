<?php

declare(strict_types=1);

namespace Src\Application\Admin\Auth\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\User\Models\User;

class AuthResource extends Resource
{
    public function __construct(
        public string $token,
        public UserResource $user,
        public bool $requires_2fa = false,
        public bool $is_first_login = false,
    ) {}

    public static function fromModel(User $user, string $token, bool $requires2fa = false, bool $isFirstLogin = false): self
    {
        return new self(
            token: $token,
            user: UserResource::fromModel($user),
            requires_2fa: $requires2fa,
            is_first_login: $isFirstLogin,
        );
    }
}
