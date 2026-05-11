<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Enums\ProcessImportBatchStatus;
use Src\Domain\Process\Models\ProcessImportBatch;

class ProcessImportBatchResource extends Resource
{
    public function __construct(
        public string $id,
        public string $file_name,
        public bool $is_private_import,
        public int $total_count,
        public int $success_count,
        public int $failed_count,
        public int $multiple_instances_count,
        public string $status,
        public string $status_label,
        /** @var list<string>|null */
        public ?array $enqueued_process_numbers,
        public ?string $completed_at,
        public string $created_at,
    ) {}

    public static function fromModel(ProcessImportBatch $batch): self
    {
        $statusEnum = ProcessImportBatchStatus::tryFrom($batch->status);

        return new self(
            id: $batch->id,
            file_name: $batch->file_name,
            is_private_import: $batch->is_private_import,
            total_count: $batch->total_count,
            success_count: $batch->success_count,
            failed_count: $batch->failed_count,
            multiple_instances_count: $batch->multiple_instances_count,
            status: $batch->status,
            status_label: $statusEnum?->getLabel() ?? $batch->status,
            enqueued_process_numbers: $batch->enqueued_process_numbers,
            completed_at: $batch->completed_at
                ? DateFormatHelper::formatDateWithTime($batch->completed_at)
                : null,
            created_at: DateFormatHelper::formatDateWithTime($batch->created_at),
        );
    }
}
