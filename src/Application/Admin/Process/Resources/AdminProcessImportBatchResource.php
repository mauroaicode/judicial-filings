<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Enums\ProcessImportBatchStatus;
use Src\Domain\Process\Models\ProcessImportBatch;

class AdminProcessImportBatchResource extends Resource
{
    /**
     * @param  list<array{process_number: string, reason: string}>  $errors
     */
    public function __construct(
        public string $id,
        public string $organization_id,
        public string $organization_name,
        public string $file_name,
        public int $total_count,
        public int $success_count,
        public int $failed_count,
        public int $multiple_instances_count,
        public string $status,
        public string $status_label,
        /** @var list<string>|null */
        public ?array $enqueued_process_numbers,
        /** @var list<array{process_number: string, reason: string}> */
        public array $errors,
        public ?string $completed_at,
        public string $created_at,
    ) {}

    public static function fromModel(ProcessImportBatch $batch): self
    {
        $statusEnum = ProcessImportBatchStatus::tryFrom($batch->status);

        $normalizedErrors = [];
        /** @var mixed $errorsRaw */
        $errorsRaw = $batch->errors;
        if (is_array($errorsRaw)) {
            foreach ($errorsRaw as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $normalizedErrors[] = [
                    'process_number' => (string) ($row['process_number'] ?? ''),
                    'reason' => (string) ($row['reason'] ?? ''),
                ];
            }
        }

        $organizationName = '';
        if ($batch->relationLoaded('organization') && $batch->getRelation('organization') !== null) {
            $organizationName = (string) $batch->getRelation('organization')->name;
        }

        return new self(
            id: $batch->id,
            organization_id: $batch->organization_id,
            organization_name: $organizationName,
            file_name: $batch->file_name,
            total_count: $batch->total_count,
            success_count: $batch->success_count,
            failed_count: $batch->failed_count,
            multiple_instances_count: $batch->multiple_instances_count,
            status: $batch->status,
            status_label: $statusEnum?->getLabel() ?? $batch->status,
            enqueued_process_numbers: $batch->enqueued_process_numbers,
            errors: $normalizedErrors,
            completed_at: $batch->completed_at
                ? DateFormatHelper::formatDateWithTime($batch->completed_at)
                : null,
            created_at: DateFormatHelper::formatDateWithTime($batch->created_at),
        );
    }
}
