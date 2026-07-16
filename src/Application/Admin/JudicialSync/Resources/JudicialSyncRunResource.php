<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Resources;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunDayMoment;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

class JudicialSyncRunResource extends Resource
{
    public function __construct(
        public string $id,
        /** Localized display string (day of week + date + time when applicable). */
        public string $started_at,
        public ?string $command_finished_at,
        public ?string $batch_finished_at,
        public ?string $radicado_filter,
        /** Machine value: judicial_branch | samai | tyba */
        public string $data_source,
        /** Localized label from `enums.judicial_sync_data_source.*`. */
        public string $data_source_label,
        public int $processes_queued,
        public ?string $laravel_batch_id,
        /** Machine value for filters and logic (see {@see \Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus}). */
        public string $status,
        /** Localized label from `enums.judicial_sync_run_status.*` (current app locale). */
        public string $status_label,
        public ?int $command_exit_code,
        public ?string $dispatch_error,
        public ?int $failed_jobs_count,
        public ?int $queue_pending_jobs,
        /** Spanish label derived from {@see JudicialSyncRun::$started_at} in app timezone (`mañana` | `tarde` | `noche`). */
        public string $moment_of_day,
        /** @see DateFormatHelper::formatDateTimeWithDayOfWeek() */
        public string $created_at,
        /** @see DateFormatHelper::formatDateTimeWithDayOfWeek() */
        public string $updated_at,
    ) {}

    public static function fromModel(JudicialSyncRun $run): self
    {
        return new self(
            id: $run->id,
            started_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->started_at),
            command_finished_at: self::formattedOrNull($run->command_finished_at),
            batch_finished_at: self::formattedOrNull($run->batch_finished_at),
            radicado_filter: $run->radicado_filter,
            data_source: $run->data_source->value,
            data_source_label: $run->data_source->getLabel(),
            processes_queued: $run->processes_queued,
            laravel_batch_id: $run->laravel_batch_id,
            status: $run->status->value,
            status_label: $run->status->getLabel(),
            command_exit_code: $run->command_exit_code,
            dispatch_error: $run->dispatch_error,
            failed_jobs_count: $run->failed_jobs_count,
            queue_pending_jobs: isset($run->queue_pending_jobs) ? (int) $run->queue_pending_jobs : null,
            moment_of_day: JudicialSyncRunDayMoment::fromStartedAt($run->started_at)->value,
            created_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->created_at),
            updated_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->updated_at),
        );
    }

    private static function formattedOrNull(?CarbonInterface $value): ?string
    {
        if (! $value instanceof \Carbon\CarbonInterface) {
            return null;
        }

        $formatted = DateFormatHelper::formatDateTimeWithDayOfWeek($value);

        return $formatted !== '' ? $formatted : null;
    }
}
