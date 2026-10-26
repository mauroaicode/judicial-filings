<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Presence\IpLocationLookup;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\Models\AppUserDailyActivity;

readonly class RecordAppUserDailyActivityService
{
    public function __construct(
        private IpLocationLookup $ipLocationLookup,
    ) {}

    public function handle(string $appUserId, ?string $ip, bool $incrementLogin): void
    {
        $activityDate = now()->toDateString();

        foreach ($this->organizationIds($appUserId) as $organizationId) {
            try {
                $this->persist($appUserId, $organizationId, $activityDate, $ip, $incrementLogin);
            } catch (UniqueConstraintViolationException) {
                $this->persist($appUserId, $organizationId, $activityDate, $ip, $incrementLogin);
            }
        }

        event(new \Src\Domain\AppUser\Events\AppUserActivityRecorded($appUserId, $incrementLogin, $activityDate));
    }

    /**
     * @return list<string|null>
     */
    private function organizationIds(string $appUserId): array
    {
        $appUser = AppUser::query()->find($appUserId);

        if (! $appUser instanceof AppUser) {
            return [null];
        }

        $organizationIds = $appUser->organizations()
            ->orderBy('organizations.id')
            ->pluck('organizations.id')
            ->all();

        return $organizationIds === [] ? [null] : array_values($organizationIds);
    }

    private function persist(
        string $appUserId,
        ?string $organizationId,
        string $activityDate,
        ?string $ip,
        bool $incrementLogin,
    ): void {
        DB::transaction(function () use ($appUserId, $organizationId, $activityDate, $ip, $incrementLogin): void {
            $activity = AppUserDailyActivity::query()
                ->where('app_user_id', $appUserId)
                ->where('activity_date', $activityDate)
                ->when(
                    $organizationId === null,
                    fn ($query) => $query->whereNull('organization_id'),
                    fn ($query) => $query->where('organization_id', $organizationId),
                )
                ->lockForUpdate()
                ->first();

            if (! $activity instanceof AppUserDailyActivity) {
                $activity = new AppUserDailyActivity([
                    'app_user_id' => $appUserId,
                    'organization_id' => $organizationId,
                    'activity_date' => $activityDate,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'login_count' => 0,
                ]);
            }

            $activity->last_seen_at = now();

            if ($incrementLogin) {
                $activity->login_count = (int) $activity->login_count + 1;
            }

            $this->applyIp($activity, $ip);
            $activity->save();
        });
    }

    private function applyIp(AppUserDailyActivity $activity, ?string $ip): void
    {
        $ip = $ip !== null ? trim($ip) : null;

        if ($ip === '') {
            $ip = null;
        }

        $ipChanged = $ip !== null && $ip !== $activity->ip_address;

        if ($ipChanged) {
            $activity->ip_address = $ip;
            $activity->country = null;
            $activity->region = null;
            $activity->city = null;
        }

        if ($activity->ip_address === null || (! $ipChanged && $activity->country !== null)) {
            return;
        }

        $location = $this->ipLocationLookup->locate($activity->ip_address);

        if (! $location instanceof \Src\Application\Shared\Presence\IpLocation || $location->isEmpty()) {
            return;
        }

        $activity->country = $location->country;
        $activity->region = $location->region;
        $activity->city = $location->city;
    }
}
