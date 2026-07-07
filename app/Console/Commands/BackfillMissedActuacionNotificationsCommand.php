<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\Services\Notification\BackfillMissedActuacionNotificationsService;

class BackfillMissedActuacionNotificationsCommand extends Command
{
    protected $signature = 'judicial:backfill-missed-notifications
                            {--organization= : Organization UUID (required)}
                            {--radicados= : Comma-separated process numbers (required)}
                            {--discovered-on= : Only actuaciones first inserted on this date (Y-m-d)}
                            {--no-digest : Create notifications only, do not send digest}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill actuación notifications skipped as historical and send a targeted digest.';

    public function handle(BackfillMissedActuacionNotificationsService $service): int
    {
        $organizationId = $this->option('organization');
        $radicados = $this->option('radicados');

        if (! is_string($organizationId) || $organizationId === '') {
            $this->error('--organization is required.');

            return self::FAILURE;
        }

        if (! is_string($radicados) || $radicados === '') {
            $this->error('--radicados is required (comma-separated).');

            return self::FAILURE;
        }

        $processNumbers = array_values(array_filter(array_map('trim', explode(',', $radicados))));
        $discoveredOn = $this->option('discovered-on');
        $dryRun = (bool) $this->option('dry-run');
        $sendDigest = ! (bool) $this->option('no-digest');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written.');
        }

        $this->info('Backfilling '.count($processNumbers).' radicado(s) for organization '.$organizationId);

        $result = $service->backfill(
            organizationId: $organizationId,
            processNumbers: $processNumbers,
            discoveredOn: is_string($discoveredOn) && $discoveredOn !== '' ? $discoveredOn : null,
            sendDigest: $sendDigest,
            dryRun: $dryRun,
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Notifications created', (string) $result->notificationsCreated],
                ['Digest sent', $result->digestSent ? 'yes' : 'no'],
                ['Actions backfilled', (string) count($result->actionsBackfilled)],
                ['Actions skipped', (string) count($result->actionsSkipped)],
            ],
        );

        if ($result->actionsBackfilled !== []) {
            $this->info('Backfilled:');
            foreach ($result->actionsBackfilled as $line) {
                $this->line('  - '.$line);
            }
        }

        if ($result->actionsSkipped !== []) {
            $this->warn('Skipped:');
            foreach ($result->actionsSkipped as $line) {
                $this->line('  - '.$line);
            }
        }

        return self::SUCCESS;
    }
}
