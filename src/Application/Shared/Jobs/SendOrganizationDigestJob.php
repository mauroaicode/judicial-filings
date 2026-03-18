<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\Shared\Services\Notification\NotificationDigestService;
use Src\Domain\Organization\Models\Organization;

class SendOrganizationDigestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Organization $organization
    ) {
        $this->queue = config('judicial-sync.queues.email', 'notifications-email');
    }

    public function handle(NotificationDigestService $digestService): void
    {
        $digestService->sendDigest($this->organization);
    }
}
