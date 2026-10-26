<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Src\Application\Admin\Presence\PresenceChannelRoster;
use Src\Application\Admin\Presence\Resources\AppUserDailyActivityResource;
use Src\Domain\AppUser\Models\AppUserDailyActivity;

readonly class ListTodayAppUserActivityService
{
    public function __construct(
        private PresenceChannelRoster $presenceChannelRoster,
    ) {}

    public function handle(?string $organizationId, string $from, string $to, int $perPage, int $page): LengthAwarePaginator
    {
        $onlineIds = $this->presenceChannelRoster->memberIds();
        $onlineLookup = array_fill_keys($onlineIds ?? [], true);

        $paginator = AppUserDailyActivity::query()
            ->with(['appUser.organizations', 'organization'])
            ->whereBetween('activity_date', [$from, $to])
            ->when(
                $organizationId !== null,
                fn ($query) => $query->where('organization_id', $organizationId),
            )
            ->latest('activity_date')
            ->latest('last_seen_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->through(
            fn (AppUserDailyActivity $activity): AppUserDailyActivityResource => AppUserDailyActivityResource::fromModel(
                $activity,
                $onlineIds !== null && isset($onlineLookup[$activity->app_user_id]),
            )
        );

        return $paginator;
    }
}
