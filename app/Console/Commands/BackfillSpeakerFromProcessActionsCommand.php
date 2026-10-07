<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Process\Services\BackfillSpeakerFromProcessActionsService;

class BackfillSpeakerFromProcessActionsCommand extends Command
{
    protected $signature = 'process-speaker:backfill-from-actions
        {--dry-run : Count updates without writing them}
        {--chunk=200 : Number of processes processed per batch}';

    protected $description = 'Update process speaker from the latest "Cambio de ponente" action';

    public function handle(BackfillSpeakerFromProcessActionsService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $counts = $service->handle($dryRun, $chunkSize);

        $this->components->info($dryRun
            ? 'Speaker backfill simulation completed.'
            : 'Speaker backfill completed.');
        $this->line('Processes scanned: '.$counts['scanned']);
        $this->line(($dryRun ? 'Would update' : 'Updated').': '.$counts['updated']);
        $this->line('Skipped: '.$counts['skipped']);

        return self::SUCCESS;
    }
}
