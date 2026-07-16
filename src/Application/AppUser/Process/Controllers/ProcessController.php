<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\AppUser\Process\Data\StoreProcessData;
use Src\Application\AppUser\Process\DTOs\RegisterProcessResult;
use Src\Application\AppUser\Process\Resources\ProcessResource;
use Src\Application\AppUser\Process\Resources\ProcessSubjectResource;
use Src\Application\AppUser\Process\Services\DispatchProcessRegistrationService;
use Src\Application\AppUser\Process\Services\DispatchSamaiProcessRegistrationService;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\AppUser\Process\Services\ProcessFinderService;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Application\AppUser\Process\Services\RegisterSamaiProcessService;
use Src\Application\AppUser\Process\Services\SmartProcessRegistrationResolverService;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
use Src\Application\Shared\Process\Data\ToggleProcessStatusData;
use Src\Application\Shared\Process\Resources\ProcessDetailResource;
use Src\Application\Shared\Process\Services\ToggleProcessStatusService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;
use Throwable;

readonly class ProcessController
{
    public function __construct(
        private ProcessFinderService $processFinderService,
        private ProcessDetailService $processDetailService,
        private ToggleProcessStatusService $toggleProcessStatusService,
        private SmartProcessRegistrationResolverService $smartProcessRegistrationResolverService,
        private DispatchProcessRegistrationService $dispatchProcessRegistrationService,
        private RegisterProcessService $registerProcessService,
        private DispatchSamaiProcessRegistrationService $dispatchSamaiProcessRegistrationService,
        private RegisterSamaiProcessService $registerSamaiProcessService,
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

        $filters = ProcessFilterData::validateAndCreate($request->query());
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

        $uniqueSubjects = ProcessSubjectIdentityHelper::deduplicate($process->subjects);

        $subjects = $uniqueSubjects->sort(function ($a, $b): int {
            $getPriority = function ($type): int {
                $type = mb_strtoupper((string) $type);
                if (str_contains($type, mb_strtoupper(ProcessSubject::TYPE_PLAINTIFF))) {
                    return 1;
                }

                if (str_contains($type, mb_strtoupper(ProcessSubject::TYPE_DEFENDANT))) {
                    return 2;
                }

                return 3;
            };

            $pA = $getPriority($a->subject_type);
            $pB = $getPriority($b->subject_type);

            if ($pA !== $pB) {
                return $pA <=> $pB;
            }

            return strcasecmp((string) $a->name_or_business_name, (string) $b->name_or_business_name);
        })->map(fn (ProcessSubject $subject): array => ProcessSubjectResource::fromModel($subject)->toArray());

        return response()->json([
            'process' => ProcessDetailResource::fromModel($process, $organization->id)->toArray(),
            'subjects' => $subjects->values()->all(),
            'subjects_summary' => ProcessSubjectSummaryHelper::summarize($uniqueSubjects),
        ]);
    }

    /**
     * Registra un nuevo proceso para la organización del usuario.
     *
     * La fuente de datos (Rama Judicial o SAMAI) se detecta automáticamente:
     * el sistema consulta primero la Rama Judicial y, si el proceso es privado
     * o no existe allí, intenta con SAMAI.
     *
     * En ambos casos el alta es inline si el historial es corto, o asíncrona
     * (cola) si el historial es largo.
     *
     * @throws Throwable
     */
    public function store(StoreProcessData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $routing = $this->smartProcessRegistrationResolverService->handle($data->process_number, $organization->id);

        // Alta asíncrona (historial largo → encolar).
        if ($routing->deferToQueue) {
            if ($routing->source === ProcessDataSourceSlug::Samai) {
                $this->dispatchSamaiProcessRegistrationService->handle($data, $organization, $appUser);
            } else {
                $this->dispatchProcessRegistrationService->handle($data, $organization, $appUser);
            }

            return response()->json(['message' => __('process.registration_dispatched')], 201);
        }

        // Alta inline.
        if ($routing->source === ProcessDataSourceSlug::Samai) {
            $result = $this->registerSamaiProcessService->handle(
                $data->process_number,
                $organization->id,
                $data->lawyer_role,
                '',
                $appUser->id,
            );
        } else {
            $result = $this->registerProcessService->handle(
                $data->process_number,
                $organization->id,
                $data->lawyer_role,
                '',
                $appUser->id,
                $routing->prefetchedJbProcesses,
            );
        }

        return response()->json([
            'message' => $this->buildRegistrationMessage($result),
            'process' => $result->getFirstProcess() instanceof Process ? ProcessResource::fromModel($result->getFirstProcess(), $organization->id) : null,
            'processes' => $result->processes->map(fn (Process $p): ProcessResource => ProcessResource::fromModel($p, $organization->id)),
            'has_multiple_instances' => $result->hasMultipleInstances,
            'total_processes' => $result->totalProcesses,
            'registered_count' => $result->registeredCount,
            'private_count' => $result->privateCount,
        ], 201);
    }

    /**
     * Activate or deactivate a process for the authenticated user's organization.
     */
    public function toggleStatus(ToggleProcessStatusData $data, string $id): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();

        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $this->toggleProcessStatusService->handle($id, $organization->id, $data->is_active);

        $message = $data->is_active
            ? __('process.activated_successfully')
            : __('process.deactivated_successfully');

        return response()->json(['message' => $message]);
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
