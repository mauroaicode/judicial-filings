<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Media\Models\Media;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionDocumentHistoryResource extends Resource
{
    public function __construct(
        public int $id,
        public string $original_filename,
        public int $size_bytes,
        public string $mime_type,
        public ?string $created_at,
        public string $action_id,
        public string $action,
        public string $action_date,
    ) {}

    public static function fromMedia(Media $media): self
    {
        $action = $media->model;
        if (! $action instanceof ProcessAction) {
            abort(404, __('process.document_not_found'));
        }

        return new self(
            id: (int) $media->id,
            original_filename: $media->file_name,
            size_bytes: (int) $media->size,
            mime_type: (string) ($media->mime_type ?? 'application/pdf'),
            created_at: $media->created_at
                ? DateFormatHelper::formatDateTimeWithDayOfWeek($media->created_at)
                : null,
            action_id: $action->id,
            action: (string) $action->action,
            action_date: DateFormatHelper::formatDateWithDayOfWeek($action->action_date),
        );
    }
}
