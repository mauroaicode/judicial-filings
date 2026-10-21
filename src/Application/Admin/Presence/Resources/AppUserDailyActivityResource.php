<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;

class AppUserDailyActivityResource extends Resource
{
    /**
     * @param  list<array{id: string, name: string}>  $organizations
     */
    public function __construct(
        public string $id,
        public string $app_user_id,
        public ?string $organization_id,
        public ?string $organization_name,
        public string $name,
        public string $last_name,
        public string $email,
        public string $identification,
        public array $organizations,
        public string $activity_date,
        public string $first_seen_at,
        public string $last_seen_at,
        public int $login_count,
        public ?string $ip_address,
        public ?string $country,
        public ?string $region,
        public ?string $city,
        public ?string $place,
        public bool $is_online,
    ) {}

    public static function fromModel(AppUserDailyActivity $activity, bool $isOnline): self
    {
        $appUser = $activity->appUser;

        return new self(
            id: $activity->id,
            app_user_id: $activity->app_user_id,
            organization_id: $activity->organization_id,
            organization_name: $activity->organization?->name,
            name: $appUser instanceof AppUser ? $appUser->name : '',
            last_name: $appUser instanceof AppUser ? $appUser->last_name : '',
            email: $appUser instanceof AppUser ? $appUser->email : '',
            identification: $appUser instanceof AppUser ? (string) $appUser->identification : '',
            organizations: self::organizations($appUser),
            activity_date: $activity->activity_date->toDateString(),
            first_seen_at: $activity->first_seen_at->format('Y-m-d H:i:s'),
            last_seen_at: $activity->last_seen_at->format('Y-m-d H:i:s'),
            login_count: (int) $activity->login_count,
            ip_address: $activity->ip_address,
            country: $activity->country,
            region: $activity->region,
            city: $activity->city,
            place: self::place($activity->city, $activity->region, $activity->country),
            is_online: $isOnline,
        );
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private static function organizations(?AppUser $appUser): array
    {
        if (! $appUser instanceof AppUser) {
            return [];
        }

        return $appUser->organizations
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
            ])
            ->values()
            ->all();
    }

    private static function place(?string $city, ?string $region, ?string $country): ?string
    {
        $parts = array_values(array_filter(
            [$city, $region, $country],
            fn (?string $part): bool => $part !== null && trim($part) !== '',
        ));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
