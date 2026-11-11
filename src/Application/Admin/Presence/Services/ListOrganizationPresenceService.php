<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Presence\OrganizationPresenceResult;
use Src\Application\Admin\Presence\Resources\OrganizationPresenceResource;
use Src\Domain\AppUser\Models\AppUserDailyActivity;
use Src\Domain\Organization\Models\Organization;

readonly class ListOrganizationPresenceService
{
    public function handle(
        string $status,
        int $inactiveDays,
        ?bool $isActive,
        ?string $search,
        int $perPage,
        int $page,
    ): OrganizationPresenceResult {
        $base = $this->baseQuery($isActive, $search);
        $staleBefore = now()->subDays($inactiveDays);

        $never = (int) (clone $base)->whereNull('activity.last_seen_at')->count('organizations.id');
        $stale = (int) (clone $base)
            ->whereNotNull('activity.last_seen_at')
            ->where('activity.last_seen_at', '<=', $staleBefore)
            ->count('organizations.id');
        $recent = (int) (clone $base)
            ->whereNotNull('activity.last_seen_at')
            ->where('activity.last_seen_at', '>', $staleBefore)
            ->count('organizations.id');

        $filtered = $this->applyStatus(clone $base, $status, $staleBefore);
        $this->applyOrder($filtered, $status);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $filtered->paginate($perPage, ['*'], 'page', $page);
        $paginator->through(
            fn (object $row): OrganizationPresenceResource => OrganizationPresenceResource::fromRow($row, $inactiveDays)
        );

        return new OrganizationPresenceResult(
            organizations: $paginator,
            status: $status,
            inactiveDays: $inactiveDays,
            isActive: $isActive,
            search: $search,
            never: $never,
            stale: $stale,
            recent: $recent,
        );
    }

    private function baseQuery(?bool $isActive, ?string $search): Builder
    {
        $lastActivity = AppUserDailyActivity::query()
            ->select('organization_id')
            ->selectRaw('MAX(last_seen_at) as last_seen_at')
            ->whereNotNull('organization_id')
            ->groupBy('organization_id');

        $usersCount = DB::table('app_user_organization')
            ->select('organization_id')
            ->selectRaw('COUNT(*) as users_count')
            ->groupBy('organization_id');

        $query = Organization::query()
            ->leftJoinSub($lastActivity, 'activity', 'activity.organization_id', '=', 'organizations.id')
            ->leftJoinSub($usersCount, 'members', 'members.organization_id', '=', 'organizations.id')
            ->select([
                'organizations.id',
                'organizations.name',
                'organizations.is_active',
                DB::raw('COALESCE(members.users_count, 0) as users_count'),
                'activity.last_seen_at',
            ]);

        if ($isActive !== null) {
            $query->where('organizations.is_active', $isActive);
        }

        if ($search !== null && $search !== '') {
            $query->where('organizations.name', 'like', '%'.$search.'%');
        }

        return $query;
    }

    private function applyStatus(Builder $query, string $status, \Illuminate\Support\Carbon $staleBefore): Builder
    {
        return match ($status) {
            'never' => $query->whereNull('activity.last_seen_at'),
            'stale' => $query
                ->whereNotNull('activity.last_seen_at')
                ->where('activity.last_seen_at', '<=', $staleBefore),
            default => $query,
        };
    }

    private function applyOrder(Builder $query, string $status): void
    {
        if ($status === 'never') {
            $query->orderBy('organizations.name');

            return;
        }

        $query
            ->orderByRaw('activity.last_seen_at is null desc')
            ->oldest('activity.last_seen_at')
            ->orderBy('organizations.name');
    }
}
