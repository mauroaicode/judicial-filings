<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Process\Services\ProcessInstancesService;
use Src\Domain\AppUser\Models\AppUser;

readonly class ProcessInstancesController
{
    public function __construct(
        private ProcessInstancesService $processInstancesService
    ) {}

    /**
     * Return all instances (same process_number) registered for the organization,
     * so the frontend can render a switcher in the process detail view.
     */
    public function index(string $processId): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $instances = $this->processInstancesService->handle($processId, $organization->id);

        if ($instances->isEmpty()) {
            abort(404, __('process.not_found'));
        }

        return response()->json($instances->all());
    }
}
