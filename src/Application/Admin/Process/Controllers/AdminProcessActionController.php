<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Application\Shared\Process\Resources\ProcessActionResource;
use Src\Application\Shared\Process\Services\ProcessActionFinderService;
use Src\Application\Shared\Process\Services\ProcessActionPairingContextService;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Services\GroupProcessActionsService;

readonly class AdminProcessActionController
{
    public function __construct(
        private ProcessActionFinderService $processActionFinderService,
        private GroupProcessActionsService $groupProcessActionsService,
        private ProcessActionPairingContextService $pairingContextService,
    ) {}

    /**
     * Display a listing of process actions for the specified process (admin view).
     *
     * Mirrors the app-user endpoint filters/pagination/shape.
     */
    public function index(Request $request, string $processId): LengthAwarePaginator
    {
        $process = Process::query()
            ->where('id', $processId)
            ->first();

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $filters = ProcessActionFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 5);

        /** @var LengthAwarePaginator $paginatedActions */
        $paginatedActions = $this->processActionFinderService->handle($processId, $filters, $perPage);

        // Avoid N+1: resource reads process.organizations for alert_level.
        /** @var \Illuminate\Database\Eloquent\Collection<int, ProcessAction> $collection */
        $collection = $paginatedActions->getCollection();
        $collection->load('process.organizations');

        $paginatedActions->setCollection($collection);

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
        $process = Process::query()
            ->where('id', $processId)
            ->first();

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
        $process = Process::query()
            ->where('id', $processId)
            ->first();

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
