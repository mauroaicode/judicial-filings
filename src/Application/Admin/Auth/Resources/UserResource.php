<?php

declare(strict_types=1);

namespace Src\Application\Admin\Auth\Resources;

use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Resource;
use Spatie\Permission\Models\Role;
use Src\Domain\User\Models\User;

class UserResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $last_name,
        public string $email,
        public ?string $slug = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?array $roles = null,
    ) {}

    public static function fromModel(User $user): self
    {
        /** @var Collection<int, Role> $userRoles */
        $userRoles = $user->roles;

        $roles = $userRoles->map(fn (Role $role): array => [
            'value' => $role->name,
            'label' => $role->name,
        ])->all();

        return new self(
            id: $user->id,
            name: $user->name,
            last_name: $user->last_name,
            email: $user->email,
            slug: $user->slug,
            phone: $user->phone,
            address: $user->address,
            roles: $roles,
        );
    }
}
