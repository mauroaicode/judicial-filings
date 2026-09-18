<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Process\Data\RegisterManualRegistrationRequestData;
use Src\Application\Shared\Services\Notification\NotifyAppUserManualRegistrationCompletedService;
use Src\Application\Shared\Services\Organization\OrganizationProcessQuotaService;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\ManualRegistrationRequest;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessSubject;

/**
 * Creates (or attaches) the private process from a pending alta-manual request and notifies the lawyer.
 */
readonly class RegisterManualProcessFromRequestService
{
    public function __construct(
        private OrganizationProcessQuotaService $organizationProcessQuotaService,
        private NotifyAppUserManualRegistrationCompletedService $notifyAppUserCompletedService,
    ) {}

    /**
     * @return array{request: ManualRegistrationRequest, process: Process}
     */
    public function handle(
        ManualRegistrationRequest $request,
        ?RegisterManualRegistrationRequestData $edits = null,
    ): array {
        if ($request->status !== ManualRegistrationRequestStatus::Pending) {
            abort(422, __('process.manual_registration_already_resolved'));
        }

        $this->applyEdits($request, $edits);

        $processClass = trim((string) ($request->process_class ?? ''));
        $plaintiffs = $request->plaintiffs ?? [];
        $defendants = $request->defendants ?? [];

        if ($processClass === '' || $plaintiffs === [] || $defendants === [] || ! $request->lawyer_role instanceof ProcessLawyerRole) {
            abort(422, __('process.manual_registration_details_incomplete'));
        }

        $process = DB::transaction(function () use ($request, $plaintiffs, $defendants): Process {
            $existing = Process::query()
                ->whereProcessNumber($request->process_number)
                ->whereHas('organizations', function (Builder $query) use ($request): void {
                    $query->where('organizations.id', $request->organization_id);
                })
                ->first();

            if ($existing instanceof Process) {
                $this->applyOptionalProcessDetails($existing, $request);
                $this->syncSubjects($existing, $plaintiffs, $defendants, $request->other_subjects ?? []);
                $this->attachOrganization($existing, $request);

                return $existing;
            }

            if (
                $this->organizationProcessQuotaService->wouldConsumeNewActiveSlot($request->organization_id, $request->process_number)
                && ! $this->organizationProcessQuotaService->canAddProcesses($request->organization_id)
            ) {
                abort(422, $this->organizationProcessQuotaService->quotaLimitReason($request->organization_id));
            }

            $process = Process::query()
                ->whereProcessNumber($request->process_number)
                ->latest()
                ->first();

            if (! $process instanceof Process) {
                $process = Process::query()->create($this->newProcessAttributes($request));
            } else {
                $this->applyOptionalProcessDetails($process, $request);
            }

            $this->attachOrganization($process, $request);
            $this->syncSubjects($process, $plaintiffs, $defendants, $request->other_subjects ?? []);

            return $process;
        });

        $request->update([
            'status' => ManualRegistrationRequestStatus::Registered,
            'resolved_at' => now(),
        ]);

        $request = $request->fresh(['appUser', 'organization']) ?? $request;

        $this->notifyAppUserCompletedService->handle($request);

        return [
            'request' => $request,
            'process' => $process,
        ];
    }

    private function applyEdits(ManualRegistrationRequest $request, ?RegisterManualRegistrationRequestData $edits): void
    {
        if (! $edits instanceof RegisterManualRegistrationRequestData) {
            return;
        }

        $payload = [];

        if ($edits->process_class !== null && trim($edits->process_class) !== '') {
            $payload['process_class'] = trim($edits->process_class);
        }

        if ($edits->lawyer_role instanceof ProcessLawyerRole) {
            $payload['lawyer_role'] = $edits->lawyer_role;
        }

        if ($edits->court !== null) {
            $payload['court'] = $this->nullableText($edits->court);
        }

        if ($edits->speaker !== null) {
            $payload['speaker'] = $this->nullableText($edits->speaker);
        }

        if ($edits->subclass_process !== null) {
            $payload['subclass_process'] = $this->nullableText($edits->subclass_process);
        }

        if ($edits->location !== null) {
            $payload['location'] = $this->nullableText($edits->location);
        }

        $plaintiffs = $edits->normalized($edits->plaintiffs);
        $defendants = $edits->normalized($edits->defendants);
        $others = $edits->normalized($edits->other_subjects);

        if ($plaintiffs !== null) {
            $payload['plaintiffs'] = $plaintiffs;
        }

        if ($defendants !== null) {
            $payload['defendants'] = $defendants;
        }

        if ($others !== null) {
            $payload['other_subjects'] = $others;
        }

        if ($payload !== []) {
            $request->update($payload);
            $request->refresh();
        }
    }

    private function nullableText(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function applyOptionalProcessDetails(Process $process, ManualRegistrationRequest $request): void
    {
        $updates = [];

        if (is_string($request->court) && $request->court !== '') {
            $updates['court'] = $request->court;
        }

        if (is_string($request->speaker) && $request->speaker !== '') {
            $updates['speaker'] = $request->speaker;
        }

        if (is_string($request->subclass_process) && $request->subclass_process !== '') {
            $updates['subclass_process'] = $request->subclass_process;
        }

        if (is_string($request->location) && $request->location !== '') {
            $updates['location'] = $request->location;
        }

        if ($updates !== []) {
            $process->update($updates);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function newProcessAttributes(ManualRegistrationRequest $request): array
    {
        $sourceId = ProcessDataSource::query()
            ->where('slug', ProcessDataSourceSlug::PublicacionesProcesales->value)
            ->where('is_active', true)
            ->value('id');

        if (! is_string($sourceId) || $sourceId === '') {
            abort(422, __('process.manual_registration_data_source_missing'));
        }

        return [
            'process_id' => null,
            'process_number' => $request->process_number,
            'court' => $request->court ?: 'Sin despacho',
            'speaker' => $request->speaker,
            'department' => __('process.private_process_import_unknown_department'),
            'process_type' => __('process.private_process_import_process_type_default'),
            'process_class' => $request->process_class,
            'subclass_process' => $request->subclass_process,
            'litigants' => $this->litigantsSummary($request),
            'process_date' => now()->toDateString(),
            'last_activity_date' => null,
            'location' => $request->location,
            'filing_content' => null,
            'is_private' => true,
            'has_multiple_instances' => false,
            'last_api_update' => null,
            'is_manual_sync' => true,
            'process_data_source_id' => $sourceId,
            'status' => 'activo',
        ];
    }

    private function litigantsSummary(ManualRegistrationRequest $request): string
    {
        $chunks = [];

        foreach ($request->plaintiffs ?? [] as $subject) {
            $chunks[] = __('process.private_process_litigant_prefix_plaintiff').$subject['name'];
        }

        foreach ($request->defendants ?? [] as $subject) {
            $chunks[] = __('process.private_process_litigant_prefix_defendant').$subject['name'];
        }

        return implode(' | ', array_slice($chunks, 0, 12));
    }

    private function attachOrganization(Process $process, ManualRegistrationRequest $request): void
    {
        OrganizationProcess::syncActiveLink($request->organization_id, $process->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE,
            'lawyer_role' => $request->lawyer_role,
        ]);
    }

    /**
     * @param  list<array{name: string, identification: string|null}>  $plaintiffs
     * @param  list<array{name: string, identification: string|null}>  $defendants
     * @param  list<array{name: string, identification: string|null}>  $others
     */
    private function syncSubjects(Process $process, array $plaintiffs, array $defendants, array $others): void
    {
        foreach ($plaintiffs as $subject) {
            $this->attachSubjectIfMissing($process, $subject, ProcessSubject::TYPE_PLAINTIFF);
        }

        foreach ($defendants as $subject) {
            $this->attachSubjectIfMissing($process, $subject, ProcessSubject::TYPE_DEFENDANT);
        }

        foreach ($others as $subject) {
            $this->attachSubjectIfMissing($process, $subject, 'Otro');
        }
    }

    /**
     * @param  array{name: string, identification: string|null}  $subject
     */
    private function attachSubjectIfMissing(Process $process, array $subject, string $type): void
    {
        $name = trim($subject['name']);
        if ($name === '') {
            return;
        }

        $process->load('subjects');

        foreach ($process->subjects as $existing) {
            if ($existing->subject_type === $type && mb_strtolower(trim((string) $existing->name_or_business_name)) === mb_strtolower($name)) {
                return;
            }
        }

        $created = ProcessSubject::query()->create([
            'subject_registration_id' => null,
            'subject_type' => $type,
            'is_cited' => false,
            'identification' => $subject['identification'],
            'name_or_business_name' => $name,
        ]);

        $process->subjects()->attach($created->id);
        $process->unsetRelation('subjects');
    }
}
