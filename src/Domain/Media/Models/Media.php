<?php

declare(strict_types=1);

namespace Src\Domain\Media\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Src\Domain\Organization\Models\Organization;

/**
 * @property-read int $id
 * @property-read string $model_type
 * @property-read string $model_id
 * @property-read string $collection_name
 * @property-read string $name
 * @property-read string $file_name
 * @property-read string|null $mime_type
 * @property-read string $disk
 * @property-read int $size
 */
class Media extends BaseMedia
{
    public const COLLECTION_ACTION_DOCUMENTS = 'action_documents';

    /**
     * Organizations allowed to view/download this media item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'media_organization',
            'media_id',
            'organization_id'
        )->withTimestamps();
    }
}
