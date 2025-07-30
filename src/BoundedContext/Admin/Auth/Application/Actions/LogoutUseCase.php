<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\Auth\Application\Actions;

use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent\UserModel;
use Symfony\Component\HttpFoundation\Response;

readonly class LogoutUseCase
{
    /**
     * Logout user and revoke all tokens
     */
    public function __invoke(UserModel $user): void
    {
        $user->tokens()->delete();
    }
}
