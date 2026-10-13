<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Services;

use Src\Domain\Media\Models\Media;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

readonly class ProcessActionDocumentAccessService
{
    /**
     * App-user may view/download when the org has document viewing enabled
     * and is linked to the process. Per-document ACL is not required for reading:
     * enabling the setting exposes all action documents on shared processes.
     */
    public function handle(Media $media, Organization $organization): bool
    {
        if (! $organization->can_view_action_documents) {
            return false;
        }

        if ($media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS) {
            return false;
        }

        $action = $media->model;
        if (! $action instanceof ProcessAction) {
            return false;
        }

        return $action->process()
            ->whereOrganization($organization->id)
            ->exists();
    }
}
