<?php

declare(strict_types=1);

namespace Src\Application\Shared\Presence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppUserActivityRecorder
{
    public function record(string $appUserId, ?string $ip, bool $incrementLogin): void
    {
        try {
            if ($incrementLogin) {
                $this->remember($appUserId);
                dispatch(new \Src\Application\Shared\Jobs\RecordAppUserDailyActivityJob($appUserId, $ip, true));

                return;
            }

            if (! $this->acquire($appUserId)) {
                return;
            }

            dispatch(new \Src\Application\Shared\Jobs\RecordAppUserDailyActivityJob($appUserId, $ip, false));
        } catch (Throwable $exception) {
            Log::warning('No se pudo encolar la actividad del app user.', [
                'app_user_id' => $appUserId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function acquire(string $appUserId): bool
    {
        return Cache::store($this->store())->add(
            $this->key($appUserId),
            true,
            now()->addMinutes($this->debounceMinutes()),
        );
    }

    private function remember(string $appUserId): void
    {
        Cache::store($this->store())->put(
            $this->key($appUserId),
            true,
            now()->addMinutes($this->debounceMinutes()),
        );
    }

    private function key(string $appUserId): string
    {
        return 'app-user-activity:'.$appUserId.':'.now()->toDateString();
    }

    private function store(): string
    {
        return (string) config('presence.cache_store');
    }

    private function debounceMinutes(): int
    {
        return max(1, (int) config('presence.debounce_minutes', 15));
    }
}
