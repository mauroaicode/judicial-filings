<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Process\Timeline\Services\BackfillProcessTimelineService;

class BackfillProcessTimelineCommand extends Command
{
    protected $signature = 'process-timeline:backfill
        {--dry-run : Count events without writing them}
        {--chunk=500 : Number of source records processed per batch}';

    protected $description = 'Reconstruct process timeline events from existing data';

    public function handle(BackfillProcessTimelineService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $counts = $service->handle($dryRun, $chunkSize);

        $this->components->info($dryRun ? 'Timeline backfill simulation completed.' : 'Timeline backfill completed.');
        $this->line("Events {$this->createdLabel($dryRun)}: {$counts['created']}");
        $this->line("Existing events skipped: {$counts['existing']}");

        return self::SUCCESS;
    }

    private function createdLabel(bool $dryRun): string
    {
        return $dryRun ? 'that would be created' : 'created';
    }
}
