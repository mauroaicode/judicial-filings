<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Process\Data\RegisterProcessData;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\AppUser\Process\Resources\ProcessDetailResource;
use Src\Application\AppUser\Process\Resources\ProcessSubjectResource;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\AppUser\Process\Services\ProcessFinderService;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;
use Throwable;

readonly class ProcessController
{
    public function __construct(
        private RegisterProcessService $registerProcessService,
        private ProcessFinderService $processFinderService,
        private ProcessDetailService $processDetailService
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
        $page = (int) $request->query('page', 1);

        return $this->processFinderService->handle($filters, $organization->id, $perPage, $page);
    }

    /**
     * Display the specified process detail with subjects.
     */
    public function show(string $id): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($id, $organization->id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $subjects = $process->subjects->map(fn (ProcessSubject $subject): array => ProcessSubjectResource::fromModel($subject)->toArray());

        return response()->json([
            'process' => ProcessDetailResource::fromModel($process, $organization->id)->toArray(),
            'subjects' => $subjects->values()->all(),
        ]);
    }

    /**
     * Register a new process.
     *
     * @throws Exception
     * @throws Throwable
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
            'process' => $firstProcess instanceof Process ? [
                'id' => $firstProcess->id,
                'process_number' => $firstProcess->process_number,
                'court' => $firstProcess->court,
                'term_start_date' => '-',
                'term_end_date' => '-',
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
