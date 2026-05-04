<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Models;

use Database\Factories\JudicialSyncRunFactory;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\Notification\Channels\JudicialSyncDiscordNotificationService;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\QueryBuilders\JudicialSyncRunQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

/**
 * @property-read string $id
 * @property-read Carbon $started_at
 * @property-read Carbon|null $command_finished_at
 * @property-read Carbon|null $batch_finished_at
 * @property-read string|null $radicado_filter
 * @property-read int $processes_queued
 * @property-read string|null $laravel_batch_id
 * @property-read JudicialSyncRunStatus $status
 * @property-read int|null $command_exit_code
 * @property-read string|null $dispatch_error
 * @property-read int|null $failed_jobs_count
 * @property-read int|null $queue_pending_jobs
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

    public static function startRun(?string $radicadoFilter): self
    {
        return self::query()->create([
            'started_at' => now(),
            'radicado_filter' => $radicadoFilter,
            'status' => JudicialSyncRunStatus::Started,
        ]);
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

        resolve(JudicialSyncDiscordNotificationService::class)->notifyBatchFinished($this->fresh(), $batch);
    }
}
