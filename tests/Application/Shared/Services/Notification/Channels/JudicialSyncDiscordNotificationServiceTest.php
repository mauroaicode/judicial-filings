<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Queue;
use Spatie\DiscordAlerts\Jobs\SendToDiscordChannelJob;
use Src\Application\Shared\Services\Notification\Channels\JudicialSyncDiscordNotificationService;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

beforeEach(function (): void {
    JudicialSyncRun::query()->delete();
    Queue::fake();
    config([
        'discord-alerts.webhook_urls.log_sync_daily' => 'https://discord.com/api/webhooks/123456789/abcdefghijklmnopqrstuvwxyz',
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
        'processes_queued' => 0,
        'command_finished_at' => now(),
    ]);

    app(JudicialSyncDiscordNotificationService::class)->notifyNoProcesses($run);

    Queue::assertPushed(SendToDiscordChannelJob::class, function (SendToDiscordChannelJob $job): bool {
        return str_contains($job->text, 'Sincronización Rama Judicial')
            && str_contains($job->text, 'sin trabajo pendiente');
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
