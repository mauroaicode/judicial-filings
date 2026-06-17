<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Application\Shared\Process\Resources\ProcessActionResource;
use Src\Application\Shared\Process\Services\ProcessActionFinderService;
use Src\Application\Shared\Process\Services\ProcessActionPairingContextService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\GroupProcessActionsService;

readonly class ProcessActionController
{
    public function __construct(
        private ProcessActionFinderService $processActionFinderService,
        private ProcessDetailService $processDetailService,
        private GroupProcessActionsService $groupProcessActionsService,
        private ProcessActionPairingContextService $pairingContextService,
    ) {}

    /**
     * Display a listing of process actions for the specified process.
     */
    public function index(Request $request, string $processId): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($processId, $organization->id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $filters = ProcessActionFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 5);

        $paginatedActions = $this->processActionFinderService->handle($processId, $filters, $perPage);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginatedActions */
        $offset = ($paginatedActions->currentPage() - 1) * $paginatedActions->perPage();

        $transformedItems = $paginatedActions->getCollection()
            ->map(fn (ProcessAction $action, int $key): array => ProcessActionResource::fromModel($action, $offset + $key + 1)->toArray());

        // Fetch next-page context so cross-page fijación↔auto pairs can be resolved
        $contextActions = $this->pairingContextService->handle($paginatedActions);
        $contextItems = $contextActions->map(fn (ProcessAction $action): array => ProcessActionResource::fromModel($action, 0)->toArray());

        // Agrupar Fijaciones con sus Autos (con contexto de página siguiente)
        $groupedItems = $this->groupProcessActionsService->handle($transformedItems, $contextItems->isNotEmpty() ? $contextItems : null);

        $paginatedActions->setCollection($groupedItems);

        return $paginatedActions;
    }

    /**
     * List alert keyword types present in this process's actions (for filter dropdown).
     * Returns distinct AlertActionKeyword (id, name, slug) that appear in at least one actuación of the process.
     */
    public function alertKeywords(Request $request, string $processId): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($processId, $organization->id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $keywords = AlertActionKeyword::query()
            ->whereHas('processActions', fn ($q) => $q->where('process_id', $processId))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $keywords]);
    }

    /**
     * Stats: count of actuaciones per alert keyword for this process.
     * Returns id, name, slug and count so the front can show e.g. "Sentencia: 2, Fijación Estado: 4".
     */
    public function alertKeywordStats(Request $request, string $processId): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($processId, $organization->id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $stats = DB::table('alert_actions_keywords')
            ->join('process_action_alert_action_keyword', 'alert_actions_keywords.id', '=', 'process_action_alert_action_keyword.alert_action_keyword_id')
            ->join('process_actions', 'process_actions.id', '=', 'process_action_alert_action_keyword.process_action_id')
            ->where('process_actions.process_id', $processId)
            ->selectRaw('alert_actions_keywords.id, alert_actions_keywords.name, alert_actions_keywords.slug, COUNT(process_action_alert_action_keyword.process_action_id) as count')
            ->groupBy('alert_actions_keywords.id', 'alert_actions_keywords.name', 'alert_actions_keywords.slug')
            ->orderBy('alert_actions_keywords.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $stats]);
    }
}
