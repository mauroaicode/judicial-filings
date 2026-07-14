<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Task\ProcessPendingTaskUrgencyAlertsService;

class SendTaskUrgencyAlertsCommand extends Command
{
    protected $signature = 'tasks:send-urgency-alerts
                            {--organization= : Optional organization ID to process only that organization}';

    protected $description = 'Send urgency notifications for pending tasks based on days past due date';

    public function __construct(
        private readonly ProcessPendingTaskUrgencyAlertsService $processPendingTaskUrgencyAlertsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = $this->option('organization');
        $organizationId = is_string($organizationId) && $organizationId !== '' ? $organizationId : null;

        $this->info('Processing pending task urgency alerts...');

        $result = $this->processPendingTaskUrgencyAlertsService->handle($organizationId);

        $this->info("Processed {$result['processed']} tasks, sent {$result['notified']} urgency notifications.");

        return self::SUCCESS;
    }
}
