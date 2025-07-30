<?php

namespace Core\BoundedContext\Admin\User\Domain\Repositories;

use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent\UserModel;

interface UserRepositoryInterface
{
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?UserModel;

    /**
     * Find user by email and verify password
     */
    public function findByEmailAndVerifyPassword(string $email, string $password): ?UserModel;
} 