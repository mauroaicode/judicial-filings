<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Application\Resources;

use Core\Shared\Infrastructure\Persistence\Eloquent\AppCustomer;
use Core\Shared\Infrastructure\Persistence\Eloquent\AppUser;
use Spatie\LaravelData\Resource;

class AppUserResource extends Resource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $last_name,
        public string $slug,
        public string $email,
        public ?string $profile_image,
    ) {}

    /**
     * Create AppUserResource from AppUser model
     */
    public static function fromModel(AppUser $appUser): self
    {
        return new self(
            id: $appUser->id,
            name: $appUser->name,
            last_name: $appUser->last_name,
            slug: $appUser->slug,
            email: $appUser->email,
            profile_image: $appUser->profile_image,
        );
    }
}
