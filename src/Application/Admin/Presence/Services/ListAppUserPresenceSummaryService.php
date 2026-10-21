<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Presence\AppUserPresenceSummaryResult;
use Src\Application\Admin\Presence\Resources\AppUserPresenceSummaryItemResource;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;

readonly class ListAppUserPresenceSummaryService
{
    public function handle(
        ?string $organizationId,
        string $from,
        string $to,
        int $perPage,
        int $page,
    ): AppUserPresenceSummaryResult {
        $base = $this->baseQuery($organizationId, $from, $to);

        $loginCount = (int) DB::query()
            ->fromSub(
                (clone $base)
                    ->select([
                        'app_user_id',
                        'activity_date',
                        DB::raw('MAX(login_count) as login_count'),
                    ])
                    ->groupBy('app_user_id', 'activity_date'),
                'daily_logins',
            )
            ->sum('login_count');

        $daysOnline = (int) (clone $base)
            ->distinct()
            ->count(DB::raw("CONCAT(app_user_id, '|', activity_date)"));

        $users = (int) (clone $base)->distinct()->count('app_user_id');

        $paginator = (clone $base)
            ->select([
                'app_user_id',
                'organization_id',
            ])
            ->selectRaw('SUM(login_count) as login_count')
            ->selectRaw('COUNT(*) as days_online')
            ->selectRaw('MIN(first_seen_at) as first_seen_at')
            ->selectRaw('MAX(last_seen_at) as last_seen_at')
            ->groupBy('app_user_id', 'organization_id')
            ->orderByDesc('login_count')
            ->orderBy('app_user_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items());
        $appUsers = AppUser::query()
            ->whereIn('id', $rows->pluck('app_user_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $organizations = Organization::query()
            ->whereIn('id', $rows->pluck('organization_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $paginator->through(function (object $row) use ($appUsers, $organizations): AppUserPresenceSummaryItemResource {
            $appUser = $appUsers->get($row->app_user_id);
            $organization = $row->organization_id !== null
                ? $organizations->get($row->organization_id)
                : null;

            return AppUserPresenceSummaryItemResource::fromAggregate(
                $row,
                $appUser instanceof AppUser ? $appUser : null,
                $organization instanceof Organization ? $organization : null,
            );
        });

        return new AppUserPresenceSummaryResult(
            activities: $paginator,
            from: $from,
            to: $to,
            organizationId: $organizationId,
            users: $users,
            loginCount: $loginCount,
            daysOnline: $daysOnline,
        );
    }

    /**
     * @return Builder<AppUserDailyActivity>
     */
    private function baseQuery(?string $organizationId, string $from, string $to): Builder
    {
        return AppUserDailyActivity::query()
            ->whereBetween('activity_date', [$from, $to])
            ->when(
                $organizationId !== null,
                fn (Builder $query) => $query->where('organization_id', $organizationId),
            );
    }
}
