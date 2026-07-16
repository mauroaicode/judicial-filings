<?php

declare(strict_types=1);

namespace Src\Application\Admin\JudicialSync\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;

class AdminJudicialSyncHistoryFilterData extends Data
{
    use TranslatableDataAttributesTrait;

    public function __construct(
        #[In([
            JudicialSyncRunStatus::Started->value,
            JudicialSyncRunStatus::NoProcesses->value,
            JudicialSyncRunStatus::DispatchFailed->value,
            JudicialSyncRunStatus::BatchPending->value,
            JudicialSyncRunStatus::BatchCompleted->value,
            JudicialSyncRunStatus::BatchCompletedWithFailures->value,
            JudicialSyncRunStatus::BatchCancelled->value,
        ])]
        public ?string $status = null,
        #[In([
            JudicialSyncDataSource::JudicialBranch->value,
            JudicialSyncDataSource::Samai->value,
            JudicialSyncDataSource::Tyba->value,
        ])]
        public ?string $data_source = null,
        #[Date]
        public ?string $started_at_from = null,
        #[Date]
        public ?string $started_at_to = null,
        public int $per_page = 15,
    ) {}
}
