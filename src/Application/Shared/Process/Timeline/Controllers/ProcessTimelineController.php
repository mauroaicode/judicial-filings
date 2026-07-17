<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Timeline\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Organization\Services\ResolveUserOrganizationService;
use Src\Application\Shared\Process\Timeline\Resources\ProcessTimelineEventResource;
use Src\Application\Shared\Process\Timeline\Services\ListProcessTimelineService;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessTimelineEvent;

class ProcessTimelineController
{
    public function __construct(
        private readonly ResolveUserOrganizationService $resolveUserOrganizationService,
        private readonly ListProcessTimelineService $listProcessTimelineService,
    ) {}

    public function index(string $processId, Request $request): JsonResponse
    {
        $organization = $this->resolveUserOrganizationService->handle();

        if (! $organization instanceof Organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $events = $this->listProcessTimelineService->handle(
            $processId,
            $organization->id,
            $request->integer('per_page', 20),
        );

        $events->through(
            fn (ProcessTimelineEvent $event): ProcessTimelineEventResource => ProcessTimelineEventResource::fromModel($event)
        );

        return response()->json($events);
    }
}
