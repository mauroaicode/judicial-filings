<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Resources;

use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Resource;
use Spatie\Permission\Models\Role;
use Src\Domain\AppUser\Enums\AppUserRole;
use Src\Domain\AppUser\Models\AppUser;

class AppUserResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $last_name,
        public string $email,
        public string $identification,
        public ?string $slug = null,
        public ?string $profile_image = null,
        public bool $must_change_password = true,
        public ?array $roles = null,
        public ?string $organization_id = null,
    ) {}

    public static function fromModel(AppUser $appUser): self
    {
        /** @var Collection<int, Role> $userRoles */
        $userRoles = $appUser->roles;

        $roles = $userRoles->map(fn (Role $role): array => [
            'value' => $role->name,
            'label' => AppUserRole::getLabelFor($role->name) ?? $role->name,
        ])->all();

        return new self(
            id: $appUser->id,
            name: $appUser->name,
            last_name: $appUser->last_name,
            email: $appUser->email,
            identification: $appUser->identification,
            slug: $appUser->slug,
            profile_image: $appUser->profile_image,
            must_change_password: (bool) ($appUser->must_change_password ?? true),
            roles: $roles,
            organization_id: $appUser->organizations()->first()?->id,
        );
    }
}
