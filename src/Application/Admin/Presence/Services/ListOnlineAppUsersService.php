<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Services;

use Src\Application\Admin\Presence\PresenceChannelRoster;
use Src\Application\Admin\Presence\Resources\AppUserDailyActivityResource;
use Src\Application\Admin\Presence\Resources\OnlineAppUsersResource;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;

readonly class ListOnlineAppUsersService
{
    public function __construct(
        private PresenceChannelRoster $presenceChannelRoster,
    ) {}

    public function handle(?string $organizationId = null): OnlineAppUsersResource
    {
        $memberIds = $this->presenceChannelRoster->memberIds();

        if ($memberIds === null) {
            return new OnlineAppUsersResource(
                presence_available: false,
                total: 0,
                users: [],
            );
        }

        $users = $this->users($memberIds, $organizationId);

        return new OnlineAppUsersResource(
            presence_available: true,
            total: count($users),
            users: $users,
        );
    }

    /**
     * @param  list<string>  $memberIds
     * @return list<array<string, mixed>>
     */
    private function users(array $memberIds, ?string $organizationId): array
    {
        if ($memberIds === []) {
            return [];
        }

        $appUsers = AppUser::query()
            ->with('organizations')
            ->whereIn('id', $memberIds)
            ->when(
                $organizationId !== null,
                fn ($query) => $query->whereHas(
                    'organizations',
                    fn (\Illuminate\Contracts\Database\Query\Builder $organizations) => $organizations->where('organizations.id', $organizationId),
                ),
            )
            ->get()
            ->keyBy('id');

        $activities = AppUserDailyActivity::query()
            ->with('organization')
            ->where('activity_date', now()->toDateString())
            ->whereIn('app_user_id', $appUsers->keys())
            ->when(
                $organizationId !== null,
                fn ($query) => $query->where('organization_id', $organizationId),
            )
            ->get()
            ->groupBy('app_user_id');

        $users = [];

        foreach ($memberIds as $memberId) {
            $appUser = $appUsers->get($memberId);

            if (! $appUser instanceof AppUser) {
                continue;
            }

            $activityGroup = $activities->get($appUser->id);
            $activity = $activityGroup?->first();
            $resource = $activity instanceof AppUserDailyActivity
                ? AppUserDailyActivityResource::fromModel($activity, true)
                : null;

            $users[] = [
                'id' => $appUser->id,
                'name' => $appUser->name,
                'last_name' => $appUser->last_name,
                'email' => $appUser->email,
                'identification' => (string) $appUser->identification,
                'organization_id' => $resource?->organization_id,
                'organization_name' => $resource?->organization_name,
                'organizations' => $appUser->organizations
                    ->map(fn (Organization $organization): array => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                    ])
                    ->values()
                    ->all(),
                'ip_address' => $resource?->ip_address,
                'country' => $resource?->country,
                'region' => $resource?->region,
                'city' => $resource?->city,
                'place' => $resource?->place,
                'last_seen_at' => $resource?->last_seen_at,
            ];
        }

        return $users;
    }
}
