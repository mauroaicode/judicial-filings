<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Process\Data\ProcessFilterData;
use Src\Application\AppUser\Process\Data\RegisterProcessData;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\AppUser\Process\Resources\ProcessIndexResource;
use Src\Application\AppUser\Process\Services\ProcessFinderService;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\Process;

readonly class ProcessController
{
    public function __construct(
        private RegisterProcessService $registerProcessService,
        private ProcessFinderService $processFinderService
    ) {}

    /**
     * Display a listing of processes for the authenticated user's organization.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $filters = ProcessFilterData::from($request->query());
        $perPage = (int) $request->query('per_page', 20);

        $paginatedProcesses = $this->processFinderService->handle($filters, $organization->id, $perPage);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginatedProcesses */
        $transformedItems = $paginatedProcesses->getCollection()->map(fn (Process $process): array => ProcessIndexResource::fromModel($process, $organization->id)->toArray());

        $paginatedProcesses->setCollection($transformedItems);

        return $paginatedProcesses;
    }

    /**
     * Register a new process.
     *
     * @throws Exception
     */
    public function store(RegisterProcessData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $result = $this->registerProcessService->handle(
            $data->process_number,
            $organization->id
        );

        $message = $this->buildRegistrationMessage($result);

        $firstProcess = $result->getFirstProcess();

        return response()->json([
            'message' => $message,
            'has_multiple_instances' => $result->hasMultipleInstances,
            'total_processes' => $result->totalProcesses,
            'registered_count' => $result->registeredCount,
            'private_count' => $result->privateCount,
            'process' => $firstProcess instanceof \Src\Domain\Process\Models\Process ? [
                'id' => $firstProcess->id,
                'process_number' => $firstProcess->process_number,
                'court' => $firstProcess->court,
                'department' => $firstProcess->department,
            ] : null,
        ], 201);
    }

    /**
     * Build the registration message based on the result.
     */
    private function buildRegistrationMessage(RegisterProcessResult $result): string
    {
        if ($result->hasMultipleInstances) {
            if ($result->privateCount > 0) {
                return $result->privateCount === 1
                    ? __('process.multiple_instances_with_one_private', [
                        'total' => $result->totalProcesses,
                        'registered' => $result->registeredCount,
                    ])
                    : __('process.multiple_instances_with_private', [
                        'total' => $result->totalProcesses,
                        'registered' => $result->registeredCount,
                        'private' => $result->privateCount,
                    ]);
            }

            return __('process.multiple_instances_registered', [
                'total' => $result->totalProcesses,
                'registered' => $result->registeredCount,
            ]);
        }

        return __('process.registered_successfully');
    }
}
