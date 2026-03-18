<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Notification\NotificationDigestService;
use Src\Domain\Organization\Models\Organization;

class SendNotificationDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'judicial:send-notification-digest
                            {--organization= : Optional organization ID to send only to that organization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send consolidated email notification digest to organizations with pending updates';

    public function __construct(
        private readonly NotificationDigestService $digestService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = $this->option('organization');

        $organizations = Organization::query()
            ->when($organizationId, fn ($q) => $q->where('id', $organizationId))
            ->get();

        $this->info("Checking " . $organizations->count() . " organizations for pending notifications...");

        $bar = $this->output->createProgressBar($organizations->count());
        $bar->start();

        foreach ($organizations as $organization) {
            $this->digestService->sendDigest($organization);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Consolidated notification digest process completed.');

        return self::SUCCESS;
    }
}
