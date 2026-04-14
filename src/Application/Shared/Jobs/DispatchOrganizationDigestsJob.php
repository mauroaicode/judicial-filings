<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Domain\Organization\Models\Organization;

class DispatchOrganizationDigestsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->queue = config('judicial-sync.jobs.send_notification.queue', 'notifications');
    }

    public function handle(): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        Log::channel($channel)->info('DispatchOrganizationDigestsJob: Starting global digest dispatch');

        // Find organizations with pending notifications
        $organizations = Organization::query()
            ->whereHas('notifications', function (\Illuminate\Contracts\Database\Query\Builder $query): void {
                $query->where('is_email_notified', false);
            })
            ->get();

        Log::channel($channel)->info('DispatchOrganizationDigestsJob: Found organizations with pending notifications', [
            'count' => $organizations->count(),
        ]);

        foreach ($organizations as $organization) {
            dispatch(new \Src\Application\Shared\Jobs\SendOrganizationDigestJob($organization));
        }

        Log::channel($channel)->info('DispatchOrganizationDigestsJob: Finished global digest dispatch');
    }
}
