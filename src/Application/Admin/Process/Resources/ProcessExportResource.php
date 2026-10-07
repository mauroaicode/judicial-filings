<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ProcessExport;

class ProcessExportResource extends Resource
{
    public function __construct(
        public string $id,
        public string $organization_id,
        public string $organization_name,
        public ?string $requested_by,
        public ?string $requested_by_name,
        public string $status,
        public string $status_label,
        /** @var array<string, mixed>|null */
        public ?array $filters,
        public int $row_count,
        public int $action_row_count,
        public ?string $file_name,
        public ?string $error_message,
        public ?string $started_at,
        public ?string $completed_at,
        public ?string $expires_at,
        public bool $downloadable,
        public ?string $download_url,
        public bool $can_rerun,
        public string $created_at,
    ) {}

    public static function fromModel(ProcessExport $export): self
    {
        $export->loadMissing(['organization', 'requestedByUser']);

        $downloadable = $export->isDownloadable();
        $requester = $export->requestedByUser;
        $requestedByName = null;
        if ($requester !== null) {
            $requestedByName = trim($requester->name.' '.$requester->last_name) ?: $requester->email;
        }

        $organizationName = '';
        if ($export->relationLoaded('organization') && $export->getRelation('organization') !== null) {
            $organizationName = (string) $export->getRelation('organization')->name;
        }

        return new self(
            id: $export->id,
            organization_id: $export->organization_id,
            organization_name: $organizationName,
            requested_by: $export->requested_by,
            requested_by_name: $requestedByName,
            status: $export->status->value,
            status_label: $export->status->getLabel(),
            filters: $export->filters,
            row_count: (int) ($export->row_count ?? 0),
            action_row_count: (int) ($export->action_row_count ?? 0),
            file_name: $export->file_name,
            error_message: $export->error_message,
            started_at: $export->started_at
                ? DateFormatHelper::formatDateWithTime($export->started_at)
                : null,
            completed_at: $export->completed_at
                ? DateFormatHelper::formatDateWithTime($export->completed_at)
                : null,
            expires_at: $export->expires_at
                ? DateFormatHelper::formatDateWithTime($export->expires_at)
                : null,
            downloadable: $downloadable,
            download_url: $downloadable
                ? route('admin.organizations.processes.exports.download', [
                    'organization' => $export->organization_id,
                    'processExport' => $export->id,
                ])
                : null,
            can_rerun: is_array($export->filters),
            created_at: DateFormatHelper::formatDateWithTime($export->created_at),
        );
    }
}
