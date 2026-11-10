<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Models;

use Database\Factories\JudicialSyncRunFactory;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Notification\Channels\JudicialSyncDiscordNotificationService;
use Src\Application\Shared\Services\Process\JudicialSyncLiveStatusBroadcaster;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\QueryBuilders\JudicialSyncRunQueryBuilder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

/**
 * @property-read string $id
 * @property-read Carbon $started_at
 * @property-read Carbon|null $command_finished_at
 * @property-read Carbon|null $batch_finished_at
 * @property-read string|null $radicado_filter
 * @property-read JudicialSyncDataSource $data_source
 * @property-read string|null $organization_id
 * @property-read Organization|null $organization
 * @property-read int $processes_queued
 * @property-read string|null $laravel_batch_id
 * @property-read JudicialSyncRunStatus $status
 * @property-read int|null $command_exit_code
 * @property-read string|null $dispatch_error
 * @property-read int|null $failed_jobs_count
 * @property-read int|null $queue_pending_jobs
 * @property-read int|null $queue_failed_jobs
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static JudicialSyncRunQueryBuilder query()
 */
class JudicialSyncRun extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'judicial_sync_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'command_finished_at',
        'batch_finished_at',
        'radicado_filter',
        'data_source',
        'organization_id',
        'processes_queued',
        'laravel_batch_id',
        'status',
        'command_exit_code',
        'dispatch_error',
        'failed_jobs_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'command_finished_at' => 'datetime',
            'batch_finished_at' => 'datetime',
            'radicado_filter' => 'string',
            'data_source' => JudicialSyncDataSource::class,
            'processes_queued' => 'integer',
            'status' => JudicialSyncRunStatus::class,
            'command_exit_code' => 'integer',
            'failed_jobs_count' => 'integer',
        ];
    }

    protected static function newFactory(): JudicialSyncRunFactory
    {
        return JudicialSyncRunFactory::new();
    }

    public function newEloquentBuilder(mixed $query): JudicialSyncRunQueryBuilder
    {
        return new JudicialSyncRunQueryBuilder($query);
    }

    /**
     * Whether a sync batch is still writing (command started or Laravel batch pending).
     * Used to defer heavy AppUser registration writes against the daily cron.
     * Classification (público vs privado) still runs inline.
     */
    public static function hasActiveBatch(?JudicialSyncDataSource $dataSource = null): bool
    {
        $query = self::query()
            ->whereIn('status', [
                JudicialSyncRunStatus::Started->value,
                JudicialSyncRunStatus::BatchPending->value,
            ])
            ->where('started_at', '>=', now()->subHours(8));

        if ($dataSource instanceof JudicialSyncDataSource) {
            $query->where('data_source', $dataSource);
        }

        return $query->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public static function startRun(
        ?string $radicadoFilter,
        JudicialSyncDataSource $dataSource = JudicialSyncDataSource::JudicialBranch,
        ?string $organizationId = null,
    ): self {
        $run = self::query()->create([
            'started_at' => now(),
            'radicado_filter' => $radicadoFilter,
            'data_source' => $dataSource,
            'organization_id' => $organizationId,
            'status' => JudicialSyncRunStatus::Started,
        ]);

        $run->broadcastLiveStatus(force: true);

        return $run;
    }

    public function markNoProcesses(): void
    {
        $this->update([
            'command_finished_at' => now(),
            'status' => JudicialSyncRunStatus::NoProcesses,
            'command_exit_code' => ConsoleCommand::SUCCESS,
            'processes_queued' => 0,
        ]);

        resolve(JudicialSyncDiscordNotificationService::class)->notifyNoProcesses($this->fresh());
        $this->broadcastLiveStatus(force: true);
    }

    public function markDispatchFailed(string $message): void
    {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        Log::channel($channel)->error('JudicialSyncRun: dispatch failed recorded', [
            'run_id' => $this->id,
            'message' => $message,
        ]);

        $this->update([
            'command_finished_at' => now(),
            'status' => JudicialSyncRunStatus::DispatchFailed,
            'command_exit_code' => ConsoleCommand::FAILURE,
            'dispatch_error' => $message,
        ]);

        resolve(JudicialSyncDiscordNotificationService::class)->notifyDispatchFailed($this->fresh());
        $this->broadcastLiveStatus(force: true);
    }

    /**
     * Persists batch metadata after dispatch. When the queue driver is `sync`, the batch may already be
     * finished before this runs ({@see Batch::$finishedAt}); in that case {@see completeBatch()} ran first
     * and we must not overwrite status with {@see JudicialSyncRunStatus::BatchPending}.
     */
    public function markBatchQueued(string $laravelBatchId, int $processesQueued, Batch $batch): void
    {
        $payload = [
            'command_finished_at' => now(),
            'laravel_batch_id' => $laravelBatchId,
            'processes_queued' => $processesQueued,
            'command_exit_code' => ConsoleCommand::SUCCESS,
        ];

        if ($batch->finishedAt === null) {
            $payload['status'] = JudicialSyncRunStatus::BatchPending;
        }

        $this->update($payload);
        $this->broadcastLiveStatus($batch, force: true);
    }

    public function completeBatch(Batch $batch): void
    {
        $finishedAt = $batch->finishedAt !== null
            ? \Illuminate\Support\Facades\Date::parse($batch->finishedAt->format(\DateTimeInterface::ATOM))
            : now();

        if ($batch->cancelledAt !== null) {
            $status = JudicialSyncRunStatus::BatchCancelled;
        } elseif ($batch->failedJobs > 0) {
            $status = JudicialSyncRunStatus::BatchCompletedWithFailures;
        } else {
            $status = JudicialSyncRunStatus::BatchCompleted;
        }

        $this->update([
            'batch_finished_at' => $finishedAt,
            'status' => $status,
            'failed_jobs_count' => $batch->failedJobs,
        ]);

        try {
            resolve(JudicialSyncDiscordNotificationService::class)->notifyBatchFinished($this->fresh(), $batch);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->error('JudicialSyncRun: Discord batch-finished notification failed', [
                    'run_id' => $this->id,
                    'message' => $e->getMessage(),
                ]);
        }

        $this->broadcastLiveStatus($batch, force: true);
    }

    /**
     * Broadcast live remaining/failed counts while the Laravel batch is processing.
     */
    public function broadcastBatchProgress(Batch $batch): void
    {
        resolve(JudicialSyncLiveStatusBroadcaster::class)->broadcastProgress($this, $batch);
    }

    private function broadcastLiveStatus(?Batch $batch = null, bool $force = false): void
    {
        resolve(JudicialSyncLiveStatusBroadcaster::class)->broadcast($this->fresh() ?? $this, $batch, $force);
    }
}
