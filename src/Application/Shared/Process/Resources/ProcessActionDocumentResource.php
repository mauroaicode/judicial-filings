<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Domain\Media\Models\Media;

class ProcessActionDocumentResource extends Resource
{
    /**
     * @param  list<string>|null  $organization_ids
     */
    public function __construct(
        public int $id,
        public string $original_filename,
        public int $size_bytes,
        public string $mime_type,
        public ?array $organization_ids = null,
    ) {}

    public static function fromMedia(Media $media, bool $includeOrganizationIds = false): self
    {
        $organizationIds = null;

        if ($includeOrganizationIds) {
            $organizationIds = $media->relationLoaded('organizations')
                ? $media->organizations->pluck('id')->values()->all()
                : $media->organizations()->pluck('organizations.id')->values()->all();
        }

        return new self(
            id: (int) $media->id,
            original_filename: $media->file_name,
            size_bytes: (int) $media->size,
            mime_type: (string) ($media->mime_type ?? 'application/pdf'),
            organization_ids: $organizationIds,
        );
    }
}
