<?php

declare(strict_types=1);

namespace Src\Application\Admin\CourtDirectory\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

class CourtDirectorySyncRunResource extends Resource
{
    public function __construct(
        public string $id,
        public string $started_at,
        public ?string $finished_at,
        public string $trigger,
        public string $trigger_label,
        public string $status,
        public string $status_label,
        public int $rows_fetched,
        public int $rows_upserted,
        public int $rows_marked_inactive,
        public ?string $error,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CourtDirectorySyncRun $run): self
    {
        return new self(
            id: $run->id,
            started_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->started_at),
            finished_at: $run->finished_at === null
                ? null
                : DateFormatHelper::formatDateTimeWithDayOfWeek($run->finished_at),
            trigger: $run->trigger->value,
            trigger_label: $run->trigger->getLabel(),
            status: $run->status->value,
            status_label: $run->status->getLabel(),
            rows_fetched: $run->rows_fetched,
            rows_upserted: $run->rows_upserted,
            rows_marked_inactive: $run->rows_marked_inactive,
            error: $run->error,
            created_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->created_at),
            updated_at: DateFormatHelper::formatDateTimeWithDayOfWeek($run->updated_at),
        );
    }
}
