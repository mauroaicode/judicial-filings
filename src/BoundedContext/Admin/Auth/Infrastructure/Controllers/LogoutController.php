<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\Auth\Infrastructure\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Core\BoundedContext\Admin\Auth\Application\Actions\LogoutUseCase;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent\UserModel;


readonly class LogoutController
{
    public function __construct(
        private LogoutUseCase $logoutUseCase
    ){
    }

    /**
     * Handle user logout
     */
    public function __invoke(): Response
    {
        /** @var UserModel $user */
        $user = Auth::user();

        if ($user) {
            ($this->logoutUseCase)($user);
        }

        return new Response(status: ResponseAlias::HTTP_OK);
    }
}
