<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Queue;
use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;
use Src\Application\Shared\Services\Notification\Channels\JudicialSyncDiscordNotificationService;
use Src\Application\Shared\Services\Notification\Channels\StaleReplicationAlertCollector;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
    Queue::fake();
    config([
        'discord-alerts.webhook_urls.log_sync_daily' => 'https://discord.com/api/webhooks/123456789/abcdefghijklmnopqrstuvwxyz',
        'discord-alerts.webhook_urls.late_sync' => '',
    ]);
});

function fakeFinishedBatch(int $totalJobs, int $failedJobs): Batch
{
    $repository = \Mockery::mock(\Illuminate\Bus\BatchRepository::class);

    return new Batch(
        \Mockery::mock(\Illuminate\Contracts\Queue\Factory::class),
        $repository,
        'batch-test-id',
        'Sync Judicial Processes Batch',
        $totalJobs,
        0,
        $failedJobs,
        [],
        [],
        \Carbon\CarbonImmutable::now(),
        null,
        \Carbon\CarbonImmutable::now(),
    );
}

it('queues discord alert for no-processes run', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::NoProcesses,
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'processes_queued' => 0,
        'command_finished_at' => now(),
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyNoProcesses($run);

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización Rama Judicial')
            && str_contains($job->text, 'sin trabajo pendiente');
    });
});

it('queues discord alert for samai no-processes run with samai title', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::NoProcesses,
        'data_source' => JudicialSyncDataSource::Samai,
        'processes_queued' => 0,
        'command_finished_at' => now(),
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyNoProcesses($run);

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización SAMAI')
            && str_contains($job->embeds[0]['description'] ?? '', 'Fuente')
            && str_contains($job->embeds[0]['description'] ?? '', 'SAMAI');
    });
});

it('queues discord alert for dispatch failure', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::DispatchFailed,
        'dispatch_error' => 'Queue connection refused',
        'command_finished_at' => now(),
        'command_exit_code' => 1,
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyDispatchFailed($run);

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'error crítico')
            && str_contains($job->embeds[0]['description'] ?? '', 'Queue connection refused');
    });
});

it('queues discord alert for finished batch with metrics', function (): void {
    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'processes_queued' => 10,
        'failed_jobs_count' => 0,
        'command_finished_at' => now(),
        'batch_finished_at' => now(),
        'laravel_batch_id' => 'uuid-batch-1',
    ]);

    $batch = fakeFinishedBatch(10, 0);

    app(JudicialSyncDiscordNotificationService::class)->notifyBatchFinished($run, $batch);

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        $names = collect($job->embeds[0]['fields'] ?? [])->pluck('name')->all();

        return str_contains($job->text, 'completada')
            && in_array('Cronología', $names, true)
            && in_array('Jobs en batch', $names, true)
            && in_array('Registro del ciclo (BD)', $names, true);
    });
});

it('queues late-sync discord alert with stale radicados after batch', function (): void {
    config([
        'discord-alerts.webhook_urls.late_sync' => 'https://discord.com/api/webhooks/123456789/late-sync-abcdefghijklmnopqrstuvwxyz',
    ]);

    app(StaleReplicationAlertCollector::class)->remember([
        'process_number' => '76109333300220240012000',
        'consulted_at' => '2026-06-17 13:10:20 COT',
        'replicated_at' => '2026-06-12 18:33:11 COT',
        'lag_hours' => 114,
        'court' => 'JUZGADO 002 ADMINISTRATIVO',
    ]);

    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'data_source' => JudicialSyncDataSource::JudicialBranch,
        'processes_queued' => 10,
        'failed_jobs_count' => 0,
        'command_finished_at' => now(),
        'batch_finished_at' => now(),
        'laravel_batch_id' => 'uuid-batch-late',
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyBatchFinished($run, fakeFinishedBatch(10, 0));

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización tardía')
            && str_contains($job->text, '76109333300220240012000') === false
            && str_contains($job->embeds[0]['fields'][3]['value'] ?? '', '76109333300220240012000');
    });
});

it('does not send late-sync alert for samai batches', function (): void {
    config([
        'discord-alerts.webhook_urls.late_sync' => 'https://discord.com/api/webhooks/123456789/late-sync-abcdefghijklmnopqrstuvwxyz',
    ]);

    app(StaleReplicationAlertCollector::class)->remember([
        'process_number' => '76109333300220240012000',
        'consulted_at' => '2026-06-17 13:10:20 COT',
        'replicated_at' => '2026-06-12 18:33:11 COT',
        'lag_hours' => 114,
        'court' => null,
    ]);

    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::BatchCompleted,
        'data_source' => JudicialSyncDataSource::Samai,
        'processes_queued' => 2,
        'command_finished_at' => now(),
        'batch_finished_at' => now(),
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyBatchFinished($run, fakeFinishedBatch(2, 0));

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización SAMAI');
    });

    Queue::assertNotPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización tardía');
    });
});
