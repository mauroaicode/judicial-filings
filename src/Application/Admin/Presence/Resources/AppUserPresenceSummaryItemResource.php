<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;

class AppUserPresenceSummaryItemResource extends Resource
{
    public function __construct(
        public string $app_user_id,
        public ?string $organization_id,
        public ?string $organization_name,
        public string $name,
        public string $last_name,
        public string $email,
        public string $identification,
        public int $login_count,
        public int $days_online,
        public ?string $first_seen_at,
        public ?string $last_seen_at,
    ) {}

    public static function fromAggregate(
        object $row,
        ?AppUser $appUser,
        ?Organization $organization,
    ): self {
        return new self(
            app_user_id: (string) $row->app_user_id,
            organization_id: $row->organization_id !== null ? (string) $row->organization_id : null,
            organization_name: $organization?->name,
            name: $appUser instanceof AppUser ? $appUser->name : '',
            last_name: $appUser instanceof AppUser ? $appUser->last_name : '',
            email: $appUser instanceof AppUser ? $appUser->email : '',
            identification: $appUser instanceof AppUser ? (string) $appUser->identification : '',
            login_count: (int) $row->login_count,
            days_online: (int) $row->days_online,
            first_seen_at: self::timestamp($row->first_seen_at ?? null),
            last_seen_at: self::timestamp($row->last_seen_at ?? null),
        );
    }

    private static function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
