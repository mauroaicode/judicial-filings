<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Repositories;

use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\AppUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

readonly class AppUserRepository implements AppUserRepositoryInterface
{
    public function __construct(
        private AppUser $appUser
    ) {}

    public function findAll(): Collection
    {
        return $this->appUser::query()->get();
    }

    public function findById(string $id): ?AppUser
    {
        return $this->appUser::query()->find($id);
    }

    public function findByEmail(string $email): ?AppUser
    {
        return $this->appUser::query()
            ->where('email', $email)
            ->first();
    }

    public function findByEmailAndVerifyPassword(string $email, string $password): ?AppUser
    {
        $appUser = $this->findByEmail($email);

        if (!$appUser || !Hash::check($password, $appUser->password)) {
            return null;
        }

        return $appUser;
    }

    public function create(array $data): AppUser
    {
        return $this->appUser::query()->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $updated = $this->appUser::query()
            ->where('id', $id)
            ->update($data);

        return $updated > 0;
    }

    public function delete(string $id): bool
    {
        $deleted = $this->appUser::query()
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }
}
