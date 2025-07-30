<?php

namespace Core\BoundedContext\Admin\User\Infrastructure\Persistence\Repositories;

use Core\BoundedContext\Admin\User\Domain\Repositories\UserRepositoryInterface;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserModel $userModel
    ) {}

    public function findByEmail(string $email): ?UserModel
    {
        return $this->userModel::query()
            ->where('email', $email)
            ->first();
    }

    public function findByEmailAndVerifyPassword(string $email, string $password): ?UserModel
    {
        $user = $this->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
} 