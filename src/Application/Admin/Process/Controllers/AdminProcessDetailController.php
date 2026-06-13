<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Admin\Process\Resources\AdminProcessOrganizationResource;
use Src\Application\Admin\Process\Resources\AdminProcessSubjectResource;
use Src\Application\Admin\Process\Services\AdminProcessDetailService;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
use Src\Application\Shared\Process\Resources\ProcessDetailResource;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

readonly class AdminProcessDetailController
{
    public function __construct(
        private AdminProcessDetailService $adminProcessDetailService,
    ) {}

    /**
     * Display the specified process detail with subjects (admin view).
     *
     * Optionally accepts `organization_id` query param to pick which pivot context
     * (lawyer_role, inactivity_alert_level) should be used. If omitted, we use the
     * most recently attached organization (latest pivot created_at).
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $process = $this->adminProcessDetailService->handle($id);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $organizationId = (string) $request->query('organization_id', '');

        $org = $organizationId !== ''
            ? $process->organizations->firstWhere('id', $organizationId)
            : $process->organizations->sortByDesc(fn ($o) => $o->pivot?->created_at)->first();

        if (! $org || ! $org->pivot) {
            // Admin can see the process, but it has no organization linkage for pivot context.
            // Keep response shape; just return null for alert_level/lawyer_role.
            $organizationId = '';
        } else {
            $organizationId = (string) $org->id;
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
        })->map(fn (ProcessSubject $subject): array => AdminProcessSubjectResource::fromModel($subject)->toArray());

        $firstOrganization = $process->organizations->first();
        $contextOrganizationId = $organizationId !== ''
            ? $organizationId
            : (string) ($firstOrganization ? $firstOrganization->id : '');

        $processPayload = ProcessDetailResource::fromModel(
            $process,
            $contextOrganizationId,
            statusActiveIfAnyOrganization: true,
        )->toArray();

        if ($organizationId === '') {
            $processPayload['alert_level'] = null;
            $processPayload['lawyer_role'] = null;
        }

        $organizationItems = $process->organizations
            ->sortByDesc(fn ($org) => $org->pivot?->interest_date)
            ->values()
            ->map(fn (\Src\Domain\Organization\Models\Organization $org): array => AdminProcessOrganizationResource::fromOrganizationAndProcess($org, $process)->toArray());

        return response()->json([
            'process' => $processPayload,
            'subjects' => $subjects->values()->all(),
            'subjects_summary' => ProcessSubjectSummaryHelper::summarize($uniqueSubjects),
            'organizations' => [
                'count' => $organizationItems->count(),
                'items' => $organizationItems->all(),
            ],
        ]);
    }
}
