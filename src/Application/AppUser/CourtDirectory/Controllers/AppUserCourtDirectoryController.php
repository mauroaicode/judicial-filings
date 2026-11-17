<?php

declare(strict_types=1);

namespace Src\Application\AppUser\CourtDirectory\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Application\Shared\CourtDirectory\Resources\CourtDirectoryEntryResource;
use Src\Application\Shared\CourtDirectory\Services\FindCourtDirectoryByDeskCodeService;
use Src\Application\Shared\CourtDirectory\Services\ListCourtDirectoryEntriesService;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;

readonly class AppUserCourtDirectoryController
{
    public function __construct(
        private ListCourtDirectoryEntriesService $listCourtDirectoryEntriesService,
        private FindCourtDirectoryByDeskCodeService $findCourtDirectoryByDeskCodeService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CourtDirectoryEntryResource>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, CourtDirectoryEntry> $paginator */
        $paginator = $this->listCourtDirectoryEntriesService->handle(
            $request->string('q')->toString(),
            $request->string('department')->toString(),
            $request->string('city')->toString(),
            $request->string('specialty')->toString(),
        );

        $paginator->through(
            fn (CourtDirectoryEntry $entry): CourtDirectoryEntryResource => CourtDirectoryEntryResource::fromModel($entry)
        );

        /** @var LengthAwarePaginator<int, CourtDirectoryEntryResource> $paginator */
        return $paginator;
    }

    public function byDeskCode(string $deskCode): JsonResponse
    {
        $entries = $this->findCourtDirectoryByDeskCodeService
            ->handle($deskCode)
            ->map(fn (CourtDirectoryEntry $entry): array => CourtDirectoryEntryResource::fromModel($entry)->toArray())
            ->values();

        return response()->json(['data' => $entries]);
    }
}
