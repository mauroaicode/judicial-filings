<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Domain\Media\Models\Media;
use Src\Domain\Process\Models\ProcessAction;

readonly class DeleteProcessActionDocumentService
{
    public function handle(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);

        if (! $media instanceof Media
            || $media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS
            || ! $media->model instanceof ProcessAction
        ) {
            abort(404, __('process.document_not_found'));
        }

        $media->delete();
    }
}
