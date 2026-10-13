<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Src\Domain\Media\Models\Media;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

readonly class SyncMediaOrganizationsService
{
    /**
     * Replace ACL organizations for a process-action document.
     * Orgs must be actively linked to the action's process.
     * Selected orgs get can_view_action_documents enabled (never turned off here).
     *
     * @param  list<string>  $organizationIds
     * @return Collection<int, string>
     */
    public function handle(Media $media, array $organizationIds): Collection
    {
        $action = $media->model;
        if (! $action instanceof ProcessAction || $media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS) {
            abort(404, __('process.document_not_found'));
        }

        $uniqueIds = collect($organizationIds)->unique()->values();
        $this->assertOrganizationsLinkedToProcess($action->process_id, $uniqueIds->all());
        $this->enableCanViewActionDocuments($uniqueIds->all());

        $media->organizations()->sync($uniqueIds->all());

        return $uniqueIds;
    }

    /**
     * @param  list<string>  $organizationIds
     */
    private function assertOrganizationsLinkedToProcess(string $processId, array $organizationIds): void
    {
        $linkedIds = Organization::query()
            ->whereIn('id', $organizationIds)
            ->whereHas('processes', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($processId): void {
                $query->where('processes.id', $processId);
            })
            ->pluck('id')
            ->all();

        $missing = array_values(array_diff($organizationIds, $linkedIds));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'organization_ids' => [__('process.documents_organizations_not_linked')],
            ]);
        }
    }

    /**
     * Checking an org in the upload modal means "grant document viewing capability".
     * Unchecking never disables the org setting — only omits them from this document ACL.
     *
     * @param  list<string>  $organizationIds
     */
    private function enableCanViewActionDocuments(array $organizationIds): void
    {
        if ($organizationIds === []) {
            return;
        }

        Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('can_view_action_documents', false)
            ->update(['can_view_action_documents' => true]);
    }
}
