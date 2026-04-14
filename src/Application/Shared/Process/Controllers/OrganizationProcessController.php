<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Shared\Process\Resources\OrganizationProcessResource;
use Src\Application\Shared\Process\Services\ListOrganizationProcessesService;
use Src\Domain\Process\Models\Process;

class OrganizationProcessController
{
    /**
     * Display a listing of processes for an organization.
     */
    public function index(
        string $organizationId,
        Request $request,
        ListOrganizationProcessesService $service
    ): JsonResponse {
        $processes = $service->handle($organizationId, $request->all());

        return response()->json(
            $processes->map(fn (Process $process): \Src\Application\Shared\Process\Resources\OrganizationProcessResource => OrganizationProcessResource::fromModel($process))
        );
    }
}
