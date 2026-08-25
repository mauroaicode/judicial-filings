<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelParseResult;
use Src\Application\Shared\Helpers\ProcessConsultationScopeHelper;
use Src\Application\Shared\Helpers\ProcessPhantomInstanceHelper;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Process\Services\FijacionEstadoActionSplitter;
use Throwable;

class PrivateProcessExcelImportService
{
    private int $actionRegistrationSeed;

    public function __construct(
        private readonly FijacionEstadoActionSplitter $fijacionEstadoActionSplitter,
    ) {
        $minAct = ProcessAction::query()->where('action_registration_id', '<', 0)->min('action_registration_id');
        $this->actionRegistrationSeed = $minAct === null ? -1 : (int) $minAct - 1;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function handle(string $organizationId, UploadedFile $file, ?string $dataSourceSlug = null, ?string $requestedByUserId = null): array
    {
        $fileName = $file->getClientOriginalName();

        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);

        if (! $organization->is_active) {
            return [
                'status' => 422,
                'body' => [
                    'message' => __('process.organization_inactive'),
                ],
            ];
        }

        $slugValue = $dataSourceSlug ?: ProcessDataSourceSlug::PublicacionesProcesales->value;
        $privateSourceUuid = $this->resolvePrivateImportDataSourceId($slugValue);

        if ($privateSourceUuid === null) {
            return [
                'status' => 422,
                'body' => [
                    'message' => __('process.private_process_import_invalid_data_source'),
                ],
            ];
        }

        $parsed = (new PrivateProcessExcelReader($file))->parse();

        if ($parsed->hasErrors()) {
            $batch = $this->persistPrivateImportBatchFailedFromRowErrors($organizationId, $requestedByUserId, $fileName, $parsed);

            return [
                'status' => 422,
                'body' => [
                    'message' => __('process.import_validation_failed'),
                    'errors' => ['rows' => $parsed->rowErrors],
                    'import_batch_id' => $batch->id,
                ],
            ];
        }

        if ($parsed->rows === []) {
            $batch = $this->persistPrivateImportBatchFailed(
                $organizationId,
                $requestedByUserId,
                $fileName,
                0,
                0,
                [
                    [
                        'process_number' => '',
                        'reason' => __('process.private_process_import_no_data_rows'),
                    ],
                ]
            );

            return [
                'status' => 422,
                'body' => [
                    'message' => __('process.private_process_import_no_data_rows'),
                    'import_batch_id' => $batch->id,
                ],
            ];
        }

        $grouped = $this->groupRows($parsed->rows);

        $createdProcesses = 0;
        $updatedProcesses = 0;
        $actionsImported = 0;

        DB::transaction(function () use ($organizationId, $privateSourceUuid, $grouped, &$createdProcesses, &$updatedProcesses, &$actionsImported): void {
            foreach ($grouped as $rows) {
                /** @var list<PrivateProcessExcelImportedRowDTO> $rows */
                $first = $rows[0];

                $process = $this->resolveProcessForImport($first, $organizationId, $privateSourceUuid);

                if (! $process instanceof Process) {
                    $process = Process::query()->create($this->newPrivateProcessAttributes($first, $privateSourceUuid));
                    $this->attachOrganization($process, $organizationId);
                    // Alta del radicado: sujetos una sola vez (primera fila), no por cada actuación.
                    $this->syncSubjectsFromRow($process, $first);
                    $this->extendLitigantsSummaryFromRow($process, $first);
                    $createdProcesses++;
                } else {
                    $this->attachOrganization($process, $organizationId);
                    // El radicado ya existe: solo actuaciones. Releer demandante/demandado
                    // duplicaría sujetos (una vez por movimiento del Excel).
                    $updatedProcesses++;
                }

                $nextCons = (int) (ProcessAction::query()
                    ->where('process_id', $process->id)
                    ->max('cons_action') ?? 0);

                foreach ($rows as $row) {
                    if (trim($row->actionText) === '') {
                        continue;
                    }

                    if ($this->actionAlreadyExists($process->id, $row)) {
                        continue;
                    }

                    $actionTitles = $this->fijacionEstadoActionSplitter->split($row->actionText);
                    if ($actionTitles === []) {
                        continue;
                    }

                    $registrationDateForAction = $row->registrationDate ?? $row->startDate ?? now()->format('Y-m-d');

                    foreach ($actionTitles as $actionTitle) {
                        $nextCons++;
                        $registrationId = $this->takeNextNegativeActionRegistrationId();

                        $action = ProcessAction::query()->create([
                            'process_id' => $process->id,
                            'action_registration_id' => $registrationId,
                            'cons_action' => max(1, $nextCons),
                            'action_date' => $registrationDateForAction,
                            'action' => $actionTitle,
                            'annotation' => $row->annotation,
                            'start_date' => $row->startDate,
                            'end_date' => $row->endDate,
                            'registration_date' => $registrationDateForAction,
                        ]);

                        $actionsImported++;
                    }

                    $this->refreshProcessActivityBoundariesAfterAction($process, $row);
                }

                $process->refresh();
            }
        });

        $batch = $this->persistPrivateImportBatchCompleted(
            $organizationId,
            $requestedByUserId,
            $fileName,
            $parsed,
            $grouped,
            $actionsImported,
        );

        return [
            'status' => 200,
            'body' => [
                'message' => __('process.private_process_import_success'),
                'processes_created' => $createdProcesses,
                'processes_updated' => $updatedProcesses,
                'actions_imported' => $actionsImported,
                'import_batch_id' => $batch->id,
            ],
        ];
    }

    /**
     * @param  array<string, list<PrivateProcessExcelImportedRowDTO>>  $grouped
     */
    private function persistPrivateImportBatchCompleted(
        string $organizationId,
        ?string $requestedByUserId,
        string $fileName,
        PrivateProcessExcelParseResult $parsed,
        array $grouped,
        int $actionsImported,
    ): ProcessImportBatch {
        $excelRows = count($parsed->rows);
        $rowsWithoutAction = count(array_filter(
            $parsed->rows,
            static fn (PrivateProcessExcelImportedRowDTO $row): bool => trim($row->actionText) === '',
        ));
        $rowsWithAction = $excelRows - $rowsWithoutAction;
        $skippedDuplicateActions = max(0, $rowsWithAction - $actionsImported);

        return ProcessImportBatch::query()->create([
            'organization_id' => $organizationId,
            'requested_by' => $requestedByUserId,
            'file_name' => $fileName,
            'is_private_import' => true,
            'excel_total_count' => $excelRows,
            'total_count' => count($grouped),
            'enqueued_process_numbers' => $this->orderedUniqueRadicados($parsed->rows),
            'success_count' => $actionsImported + $rowsWithoutAction,
            'failed_count' => $skippedDuplicateActions,
            'multiple_instances_count' => $this->multipleCourtInstanceRadicadoCount($grouped),
            'status' => ProcessImportBatch::STATUS_COMPLETED,
            'errors' => [],
            'laravel_batch_id' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  PrivateProcessExcelParseResult  $parsed  Row errors: {@see PrivateProcessExcelParseResult::$rowErrors} (Excel row => message)
     */
    private function persistPrivateImportBatchFailedFromRowErrors(
        string $organizationId,
        ?string $requestedByUserId,
        string $fileName,
        PrivateProcessExcelParseResult $parsed,
    ): ProcessImportBatch {
        $errors = [];
        foreach ($parsed->rowErrors as $excelRow => $message) {
            $errors[] = [
                'process_number' => '',
                'reason' => __('process.private_process_import_history_row_reason', [
                    'row' => (string) $excelRow,
                    'detail' => (string) $message,
                ]),
            ];
        }

        return $this->persistPrivateImportBatchFailed(
            $organizationId,
            $requestedByUserId,
            $fileName,
            count($parsed->rows),
            count($errors),
            $errors,
        );
    }

    /**
     * @param  list<array{process_number: string, reason: string}>  $errors
     */
    private function persistPrivateImportBatchFailed(
        string $organizationId,
        ?string $requestedByUserId,
        string $fileName,
        int $excelTotalCount,
        int $failedCount,
        array $errors,
    ): ProcessImportBatch {
        return ProcessImportBatch::query()->create([
            'organization_id' => $organizationId,
            'requested_by' => $requestedByUserId,
            'file_name' => $fileName,
            'is_private_import' => true,
            'excel_total_count' => $excelTotalCount,
            'total_count' => $excelTotalCount,
            'enqueued_process_numbers' => [],
            'success_count' => 0,
            'failed_count' => $failedCount,
            'multiple_instances_count' => 0,
            'status' => ProcessImportBatch::STATUS_FAILED,
            'errors' => $errors,
            'laravel_batch_id' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @return list<string>
     */
    private function orderedUniqueRadicados(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! isset($seen[$row->processNumber])) {
                $seen[$row->processNumber] = true;
                $out[] = $row->processNumber;
            }
        }

        return $out;
    }

    /**
     * Radicados with more than one distinct court (same number, several despachos) within this upload.
     *
     * @param  array<string, list<PrivateProcessExcelImportedRowDTO>>  $grouped
     */
    private function multipleCourtInstanceRadicadoCount(array $grouped): int
    {
        $courtsByRadi = [];
        foreach ($grouped as $rows) {
            /** @var PrivateProcessExcelImportedRowDTO $first */
            $first = $rows[0];
            $radi = $first->processNumber;
            $courtKey = $this->courtKey($first->court);
            $courtsByRadi[$radi] ??= [];
            $courtsByRadi[$radi][$courtKey] = true;
        }

        $n = 0;
        foreach ($courtsByRadi as $courts) {
            if (count($courts) > 1) {
                $n++;
            }
        }

        return $n;
    }

    private function takeNextNegativeActionRegistrationId(): int
    {
        $id = $this->actionRegistrationSeed;
        $this->actionRegistrationSeed--;

        return $id;
    }

    /**
     * Laborales already followed in Unificada: reuse that process instead of creating
     * a second publicaciones_procesales instance. Other small courts keep the PP path.
     */
    private function resolveProcessForImport(
        PrivateProcessExcelImportedRowDTO $first,
        string $organizationId,
        string $privateSourceUuid,
    ): ?Process {
        $existingPrivate = Process::query()
            ->where('process_number', $first->processNumber)
            ->where('court', $first->court)
            ->where('process_data_source_id', $privateSourceUuid)
            ->whereHas('organizations', fn (Builder $q) => $q->where('organizations.id', $organizationId))
            ->first();

        if ($existingPrivate instanceof Process) {
            return $existingPrivate;
        }

        if (! ProcessConsultationScopeHelper::isLaboral($first->processNumber, $first->court)) {
            return null;
        }

        $processes = Process::query()
            ->where('process_number', $first->processNumber)
            ->withCount('actions')
            ->get();

        return ProcessPhantomInstanceHelper::pickPreferredInstanceForImport($processes);
    }

    private function attachOrganization(Process $process, string $organizationId): void
    {
        $process->organizations()->syncWithoutDetaching([
            $organizationId => [
                'interest_date' => now()->toDateString(),
                'is_active' => true,
                'status' => \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value,
            ],
        ]);
    }

    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @return array<string, list<PrivateProcessExcelImportedRowDTO>>
     */
    private function groupRows(array $rows): array
    {
        /** @var array<string, list<PrivateProcessExcelImportedRowDTO>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->processNumber.'|'.$this->courtKey($row->court);
            $grouped[$key] ??= [];
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    private function courtKey(string $court): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $court) ?? ''));
    }

    private function resolvePrivateImportDataSourceId(string $slugValue): ?string
    {
        $slug = ProcessDataSourceSlug::tryFrom($slugValue);
        if ($slug === null || ! $slug->allowsPrivateExcelImport()) {
            return null;
        }

        /** @var string|null $id */
        $id = ProcessDataSource::query()
            ->whereActive()
            ->forPrivateExcelImport()
            ->whereSlug($slug->value)
            ->value('id');

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function newPrivateProcessAttributes(PrivateProcessExcelImportedRowDTO $row, string $dataSourceUuid): array
    {
        return [
            'process_id' => null,
            'process_number' => $row->processNumber,
            'court' => $row->court,
            'speaker' => null,
            'department' => __('process.private_process_import_unknown_department'),
            'process_type' => __('process.private_process_import_process_type_default'),
            'process_class' => $row->processClass,
            'subclass_process' => null,
            'litigants' => null,
            'process_date' => $row->registrationDate ?? now()->format('Y-m-d'),
            'last_activity_date' => $row->registrationDate ?? $row->startDate,
            'location' => null,
            'filing_content' => null,
            'is_private' => true,
            'has_multiple_instances' => false,
            'last_api_update' => null,
            'is_manual_sync' => true,
            'process_data_source_id' => $dataSourceUuid,
            'status' => 'activo',
        ];
    }

    private function summarizeLitigants(PrivateProcessExcelImportedRowDTO $row): string
    {
        $chunks = [];

        foreach (PrivateImportSubjectNamesSplitter::split($row->plaintiffsRaw) as $name) {
            $chunks[] = __('process.private_process_litigant_prefix_plaintiff').$name;
        }

        foreach (PrivateImportSubjectNamesSplitter::split($row->defendantsRaw) as $name) {
            $chunks[] = __('process.private_process_litigant_prefix_defendant').$name;
        }

        return implode(' | ', array_slice($chunks, 0, 12));
    }

    private function extendLitigantsSummaryFromRow(Process $process, PrivateProcessExcelImportedRowDTO $row): void
    {
        $addition = $this->summarizeLitigants($row);
        if ($addition === '') {
            return;
        }

        $existing = trim((string) ($process->litigants ?? ''));
        $merged = $existing === ''
            ? $addition
            : $existing.' | '.$addition;

        if (strlen($merged) > 6000) {
            $merged = mb_substr($merged, 0, 5997).'...';
        }

        $process->update(['litigants' => $merged]);
        $process->refresh();
    }

    private function syncSubjectsFromRow(Process $process, PrivateProcessExcelImportedRowDTO $row): void
    {
        $process->loadMissing('subjects');
        foreach (PrivateImportSubjectNamesSplitter::split($row->plaintiffsRaw) as $name) {
            $this->attachSubjectIfMissing($process, $name, ProcessSubject::TYPE_PLAINTIFF);
        }

        foreach (PrivateImportSubjectNamesSplitter::split($row->defendantsRaw) as $name) {
            $this->attachSubjectIfMissing($process, $name, ProcessSubject::TYPE_DEFENDANT);
        }
    }

    private function attachSubjectIfMissing(Process $process, string $name, string $type): void
    {
        $process->loadMissing('subjects');
        foreach ($process->subjects as $existing) {
            if ($existing->subject_type === $type && mb_strtolower(trim((string) $existing->name_or_business_name)) === mb_strtolower(trim($name))) {
                return;
            }
        }

        $subject = ProcessSubject::query()->create([
            'subject_registration_id' => null,
            'subject_type' => $type,
            'is_cited' => false,
            'identification' => null,
            'name_or_business_name' => $name,
        ]);

        $process->subjects()->attach($subject->id);
        $process->unsetRelation('subjects');
    }

    private function refreshProcessActivityBoundariesAfterAction(Process $process, PrivateProcessExcelImportedRowDTO $row): void
    {
        $reg = $row->registrationDate;
        $actDate = $row->startDate ?? $reg;

        $process->refresh();

        $updates = [];

        if ($reg !== null && $reg !== '') {
            $currentPd = $process->process_date->format('Y-m-d');
            if ($reg < $currentPd) {
                $updates['process_date'] = $reg;
            }
        }

        $currentLa = $process->last_activity_date?->format('Y-m-d');
        foreach ([$reg, $actDate, $row->endDate] as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if ($candidate === '') {
                continue;
            }

            if ($currentLa === null || $candidate > $currentLa) {
                $updates['last_activity_date'] = $candidate;
                $currentLa = $candidate;
            }
        }

        if ($updates !== []) {
            $process->update($updates);
        }
    }

    private function actionAlreadyExists(string $processId, PrivateProcessExcelImportedRowDTO $row): bool
    {
        $registrationDateForAction = $row->registrationDate ?? $row->startDate ?? now()->format('Y-m-d');

        if ($this->actionExistsWithText($processId, $registrationDateForAction, $row->actionText, $row->annotation)) {
            return true;
        }

        $parts = $this->fijacionEstadoActionSplitter->split($row->actionText);
        if (count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (! $this->actionExistsWithText($processId, $registrationDateForAction, $part, $row->annotation)) {
                return false;
            }
        }

        return true;
    }

    private function actionExistsWithText(
        string $processId,
        string $registrationDate,
        string $actionText,
        ?string $annotation,
    ): bool {
        $query = ProcessAction::query()
            ->where('process_id', $processId)
            ->whereDate('registration_date', $registrationDate)
            ->where('action', $actionText);

        if ($annotation === null || $annotation === '') {
            $query->whereNull('annotation');
        } else {
            $query->where('annotation', $annotation);
        }

        return $query->exists();
    }
}
