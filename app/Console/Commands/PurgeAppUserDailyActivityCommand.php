<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Domain\AppUser\Models\AppUserDailyActivity;

class PurgeAppUserDailyActivityCommand extends Command
{
    protected $signature = 'presence:purge-daily-activity';

    protected $description = 'Delete app user daily activity rows older than the configured retention.';

    public function handle(): int
    {
        $months = max(1, (int) config('presence.retention_months', 12));
        $cutoff = now()->subMonths($months)->toDateString();

        $deleted = AppUserDailyActivity::query()
            ->where('activity_date', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} app user activity rows before {$cutoff}.");

        return self::SUCCESS;
    }
}
