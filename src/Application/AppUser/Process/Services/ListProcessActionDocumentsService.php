<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Src\Domain\Media\Models\Media;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

readonly class ListProcessActionDocumentsService
{
    /**
     * Paginated action documents for a process.
     * Visible when the org has can_view_action_documents (process membership is checked by the controller).
     *
     * @return LengthAwarePaginator<int, Media>
     */
    public function handle(
        string $processId,
        Organization $organization,
        int $perPage = 12,
        int $page = 1,
    ): LengthAwarePaginator {
        if (! $organization->can_view_action_documents) {
            return new Paginator([], 0, $perPage, $page);
        }

        $actionMorph = (new ProcessAction)->getMorphClass();

        return Media::query()
            ->where('collection_name', Media::COLLECTION_ACTION_DOCUMENTS)
            ->where('model_type', $actionMorph)
            ->whereIn(
                'model_id',
                ProcessAction::query()
                    ->where('process_id', $processId)
                    ->select('id')
            )
            ->with(['model'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }
}
