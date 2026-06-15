<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Task\ProcessPendingTaskDueDateRemindersService;

class SendTaskDueDateRemindersCommand extends Command
{
    protected $signature = 'tasks:send-due-date-reminders
                            {--organization= : Optional organization ID to process only that organization}';

    protected $description = 'Send daily countdown reminders before task due dates based on reminder_days';

    public function __construct(
        private readonly ProcessPendingTaskDueDateRemindersService $processPendingTaskDueDateRemindersService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = $this->option('organization');
        $organizationId = is_string($organizationId) && $organizationId !== '' ? $organizationId : null;

        $this->info('Processing task due-date reminders...');

        $result = $this->processPendingTaskDueDateRemindersService->handle($organizationId);

        $this->info("Processed {$result['processed']} tasks, sent {$result['notified']} due-date reminders.");

        return self::SUCCESS;
    }
}
