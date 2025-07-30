<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\Auth\Application\Actions;

use Symfony\Component\HttpFoundation\Response;
use Core\BoundedContext\Admin\Auth\Application\Resources\LoginResource;
use Core\BoundedContext\Admin\User\Domain\Repositories\UserRepositoryInterface;


readonly class LoginUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Authenticate user with email and password
     */
    public function __invoke(string $email, string $password): LoginResource
    {
        $user = $this->userRepository->findByEmailAndVerifyPassword($email, $password);

        if (!$user) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.failed'));
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return LoginResource::fromToken($token);
    }
}
