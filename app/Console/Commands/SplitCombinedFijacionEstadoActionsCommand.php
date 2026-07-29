<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Process\SplitCombinedFijacionEstadoActionsService;

class SplitCombinedFijacionEstadoActionsCommand extends Command
{
    protected $signature = 'judicial:split-combined-fijacion-estado
                            {--radicado=* : One or more process_number values to repair}
                            {--all : Scan all combined Fijación/Notificación/Publicación Estado titles}
                            {--dry-run : Preview splits without writing}
                            {--force : Skip confirmation when using --all without --dry-run}';

    protected $description = 'Split already-imported combined titles like "Fijacion Estado Auto Admite Demanda" into two actuaciones for pairing.';

    public function handle(SplitCombinedFijacionEstadoActionsService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        /** @var list<string> $radicados */
        $radicados = array_values(array_filter(
            array_map(static fn (mixed $v): string => trim((string) $v), (array) $this->option('radicado')),
            static fn (string $v): bool => $v !== '',
        ));

        if ($all === ($radicados !== [])) {
            $this->error('Provide exactly one of --radicado=... or --all.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written.');
        }

        if ($all && ! $dryRun && ! (bool) $this->option('force')) {
            if (! $this->confirm('Scan and split combined Fijación/Notificación Estado actuaciones in the whole database?', false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $result = $service->handle($all ? null : $radicados, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Scanned candidates', (string) $result['scanned']],
                ['Split', (string) $result['split']],
                ['Skipped (already split)', (string) $result['skipped_already_split']],
                ['Skipped (not combined)', (string) $result['skipped_not_combined']],
            ]
        );

        foreach ($result['items'] as $item) {
            $prefix = $dryRun ? 'would split' : 'split';
            $this->line(sprintf(
                '  %s → %s | `%s` => [%s] + [%s]',
                $prefix,
                $item['process_number'],
                mb_substr($item['from'], 0, 80),
                $item['estado'],
                mb_substr($item['decision'], 0, 60),
            ));
        }

        if ($result['split'] === 0) {
            $this->info('Nothing to split.');
        }

        return self::SUCCESS;
    }
}
