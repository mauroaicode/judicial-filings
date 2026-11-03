<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

class JudicialSyncLiveSourceResource extends Resource
{
    public function __construct(
        public string $data_source,
        public string $data_source_label,
        public bool $is_active,
        public bool $is_executable,
        public ?string $run_id,
        public ?string $status,
        public ?string $status_label,
        public int $processes_queued,
        public ?int $queue_pending_jobs,
        public ?int $failed_jobs_count,
        public ?string $laravel_batch_id,
        public ?string $started_at,
        public ?string $radicado_filter,
    ) {}

    public static function idle(JudicialSyncDataSource $source): self
    {
        return new self(
            data_source: $source->value,
            data_source_label: $source->getLabel(),
            is_active: false,
            is_executable: $source->isExecutable(),
            run_id: null,
            status: null,
            status_label: null,
            processes_queued: 0,
            queue_pending_jobs: null,
            failed_jobs_count: null,
            laravel_batch_id: null,
            started_at: null,
            radicado_filter: null,
        );
    }

    public static function fromActiveRun(JudicialSyncRun $run): self
    {
        $pending = isset($run->queue_pending_jobs) ? (int) $run->queue_pending_jobs : null;
        $failedFromBatch = isset($run->queue_failed_jobs) ? (int) $run->queue_failed_jobs : null;

        return new self(
            data_source: $run->data_source->value,
            data_source_label: $run->data_source->getLabel(),
            is_active: true,
            is_executable: $run->data_source->isExecutable(),
            run_id: $run->id,
            status: $run->status->value,
            status_label: $run->status->getLabel(),
            processes_queued: (int) $run->processes_queued,
            queue_pending_jobs: $pending,
            failed_jobs_count: $failedFromBatch ?? $run->failed_jobs_count,
            laravel_batch_id: $run->laravel_batch_id,
            started_at: $run->started_at->toIso8601String(),
            radicado_filter: $run->radicado_filter,
        );
    }

    /**
     * @param  list<self>  $sources
     * @return array{has_active: bool, active_count: int, sources: list<array<string, mixed>>, active: list<array<string, mixed>>}
     */
    public static function wrapCollection(array $sources): array
    {
        $payload = array_map(
            static fn (self $source): array => $source->toArray(),
            $sources,
        );

        $active = array_values(array_filter(
            $payload,
            static fn (array $source): bool => ($source['is_active'] ?? false) === true,
        ));

        return [
            'has_active' => $active !== [],
            'active_count' => count($active),
            'sources' => $payload,
            'active' => $active,
        ];
    }
}
