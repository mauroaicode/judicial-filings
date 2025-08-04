<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Repositories;

use Core\Shared\Infrastructure\Persistence\Eloquent\AppUser;
use Illuminate\Database\Eloquent\Collection;

interface AppUserRepositoryInterface
{
    /**
     * Find all users
     */
    public function findAll(): Collection;

    /**
     * Find user by ID
     */
    public function findById(string $id): ?AppUser;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?AppUser;

    /**
     * Find user by email and verify password
     */
    public function findByEmailAndVerifyPassword(string $email, string $password): ?AppUser;

    /**
     * Create user
     */
    public function create(array $data): AppUser;

    /**
     * Update user
     */
    public function update(string $id, array $data): bool;

    /**
     * Delete user
     */
    public function delete(string $id): bool;
} 