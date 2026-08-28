<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;
use Src\Application\Admin\Process\DTOs\PrivateProcessExcelParseResult;
use Src\Application\Admin\Process\Resources\ProcessActuacionesImportResource;
use Src\Application\Admin\Process\Resources\ProcessActuacionesSkippedItemResource;
use Src\Application\Shared\Helpers\ProcessPhantomInstanceHelper;
use Src\Application\Shared\Services\Process\ProcessActionAlertNotificationService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Process\Services\FijacionEstadoActionSplitter;
use Throwable;

/**
 * Imports actuaciones (movements) from the standard private-process Excel template.
 *
 * - If a Process already exists for the radicado → attach actuaciones only (may feed digest).
 *   Subjects / litigants are left untouched: they were already loaded with the process history.
 * - If not → persist them in {@see UnassignedProcessAction} for later retroactive attach
 *   when the Process is created (no data loss for Publicaciones Procesales / small courts).
 *
 * Deduplication: an actuacion is skipped if a row already exists for that
 * process with the same registration_date + action text + annotation.
 * Combined "Fijación Estado Auto …" titles are split; a repeated estado half
 * is skipped silently when the Auto half is new (not listed as omitida).
 */
class ProcessActuacionesExcelImportService
{
    private int $actionRegistrationSeed;

    public function __construct(
        private readonly ProcessActionAlertNotificationService $processActionAlertNotificationService,
        private readonly FijacionEstadoActionSplitter $fijacionEstadoActionSplitter,
        private readonly PersistUnassignedProcessActionsService $persistUnassignedProcessActionsService,
    ) {
        $minAct = ProcessAction::query()->where('action_registration_id', '<', 0)->min('action_registration_id');
        $this->actionRegistrationSeed = $minAct === null ? -1 : (int) $minAct - 1;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function handle(UploadedFile $file, ?string $requestedByUserId = null): array
    {
        $fileName = $file->getClientOriginalName();

        $parsed = (new PrivateProcessExcelReader($file))->parse();

        if ($parsed->hasErrors()) {
            $batch = $this->persistBatchFailedFromRowErrors($requestedByUserId, $fileName, $parsed);

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
            $batch = $this->persistBatchFailed($requestedByUserId, $fileName, 0, 0, [
                ['process_number' => '', 'reason' => __('process.private_process_import_no_data_rows')],
            ]);

            return [
                'status' => 422,
                'body' => [
                    'message' => __('process.private_process_import_no_data_rows'),
                    'import_batch_id' => $batch->id,
                ],
            ];
        }

        $grouped = $this->groupRows($parsed->rows);

        $actionsImported = 0;
        $actionsSkipped = 0;
        $actionsStoredUnassigned = 0;
        $processesUpdated = 0;
        $unassignedProcessNumbers = [];
        /** @var list<array{process_number: string, action: string, annotation: string|null, registration_date: string|null, court: string|null, excel_row: int, reason: string, message: string}> $skippedActions */
        $skippedActions = [];

        DB::transaction(function () use (
            $grouped,
            $requestedByUserId,
            &$actionsImported,
            &$actionsSkipped,
            &$actionsStoredUnassigned,
            &$processesUpdated,
            &$unassignedProcessNumbers,
            &$skippedActions,
        ): void {
            foreach ($grouped as $rows) {
                /** @var list<PrivateProcessExcelImportedRowDTO> $rows */
                $first = $rows[0];

                $process = $this->findExistingProcess($first->processNumber);

                if (! $process instanceof Process) {
                    $stored = $this->persistUnassignedProcessActionsService->handle(
                        $rows,
                        null,
                        $requestedByUserId,
                    );
                    $actionsStoredUnassigned += $stored['stored'];
                    $actionsSkipped += $stored['skipped'];
                    foreach ($stored['skipped_actions'] as $skipped) {
                        $skippedActions[] = $skipped;
                    }

                    foreach ($stored['process_numbers'] as $processNumber) {
                        if (! in_array($processNumber, $unassignedProcessNumbers, true)) {
                            $unassignedProcessNumbers[] = $processNumber;
                        }
                    }

                    continue;
                }

                $processesUpdated++;

                $result = $this->importActuaciones($process, $rows);
                $actionsImported += $result['imported'];
                $actionsSkipped += count($result['skipped']);
                foreach ($result['skipped'] as $skipped) {
                    $skippedActions[] = $skipped;
                }
            }
        });

        $batch = $this->persistBatchCompleted(
            $requestedByUserId,
            $fileName,
            $parsed,
            $grouped,
            $actionsImported + $actionsStoredUnassigned,
        );

        $skippedResources = array_map(
            static fn (array $item): ProcessActuacionesSkippedItemResource => new ProcessActuacionesSkippedItemResource(
                process_number: $item['process_number'],
                action: $item['action'],
                annotation: $item['annotation'],
                registration_date: $item['registration_date'],
                court: $item['court'],
                excel_row: $item['excel_row'],
                reason: $item['reason'],
                message: $item['message'],
            ),
            $skippedActions,
        );

        return [
            'status' => 200,
            'body' => (new ProcessActuacionesImportResource(
                actions_imported: $actionsImported,
                actions_skipped: $actionsSkipped,
                actions_stored_unassigned: $actionsStoredUnassigned,
                processes_updated: $processesUpdated,
                unassigned_count: count($unassignedProcessNumbers),
                unassigned_process_numbers: $unassignedProcessNumbers,
                skipped_actions: $skippedResources,
                import_batch_id: $batch->id,
            ))->toArray(),
        ];
    }

    // ─── Process resolution ──────────────────────────────────────────────────

    private function findExistingProcess(string $processNumber): ?Process
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->withCount('actions')
            ->get();

        return ProcessPhantomInstanceHelper::pickPreferredInstanceForImport($processes);
    }

    // ─── Actuaciones ─────────────────────────────────────────────────────────

    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @return array{
     *     imported: int,
     *     skipped: list<array{
     *         process_number: string,
     *         action: string,
     *         annotation: string|null,
     *         registration_date: string|null,
     *         court: string|null,
     *         excel_row: int,
     *         reason: string,
     *         message: string
     *     }>
     * }
     */
    private function importActuaciones(Process $process, array $rows): array
    {
        $nextCons = (int) (ProcessAction::query()
            ->where('process_id', $process->id)
            ->max('cons_action') ?? 0);

        $imported = 0;
        /** @var list<array{process_number: string, action: string, annotation: string|null, registration_date: string|null, court: string|null, excel_row: int, reason: string, message: string}> $skipped */
        $skipped = [];

        foreach ($rows as $row) {
            if (trim($row->actionText) === '') {
                continue;
            }

            $registrationDate = $row->registrationDate ?? $row->startDate ?? now()->format('Y-m-d');

            if ($this->actionAlreadyExists($process->id, $row)) {
                $skipped[] = [
                    'process_number' => $row->processNumber,
                    'action' => $row->actionText,
                    'annotation' => $row->annotation,
                    'registration_date' => $registrationDate,
                    'court' => $row->court !== '' ? $row->court : null,
                    'excel_row' => $row->excelRowNumber,
                    'reason' => 'duplicate',
                    'message' => __('process.actuaciones_import_skipped_duplicate'),
                ];

                continue;
            }

            $actionTitles = $this->fijacionEstadoActionSplitter->split($row->actionText);
            if ($actionTitles === []) {
                continue;
            }

            $isSplitPair = count($actionTitles) > 1;
            $storedFromRow = 0;
            $meaningfulSkips = 0;
            $processReloaded = null;

            foreach ($actionTitles as $actionTitle) {
                if ($this->actionExistsWithText($process->id, $registrationDate, $actionTitle, $row->annotation)) {
                    if ($isSplitPair && $this->fijacionEstadoActionSplitter->isEstadoPairLabel($actionTitle)) {
                        continue;
                    }

                    $meaningfulSkips++;

                    continue;
                }

                $nextCons++;

                $action = ProcessAction::query()->create([
                    'process_id' => $process->id,
                    'action_registration_id' => $this->takeNextNegativeActionRegistrationId(),
                    'cons_action' => max(1, $nextCons),
                    'action_date' => $registrationDate,
                    'action' => $actionTitle,
                    'annotation' => $row->annotation,
                    'start_date' => $row->startDate,
                    'end_date' => $row->endDate,
                    'registration_date' => $registrationDate,
                ]);

                $imported++;
                $storedFromRow++;

                $processReloaded ??= Process::query()->whereKey($process->id)->with('organizations')->first();
                if ($processReloaded instanceof Process) {
                    // Manual Excel import: always queue digest rows even when registration_date is old.
                    $this->processActionAlertNotificationService->handle($action, $processReloaded, forceIncludeHistorical: true);
                }
            }

            if ($storedFromRow === 0 && $meaningfulSkips > 0) {
                $skipped[] = [
                    'process_number' => $row->processNumber,
                    'action' => $row->actionText,
                    'annotation' => $row->annotation,
                    'registration_date' => $registrationDate,
                    'court' => $row->court !== '' ? $row->court : null,
                    'excel_row' => $row->excelRowNumber,
                    'reason' => 'duplicate',
                    'message' => __('process.actuaciones_import_skipped_duplicate'),
                ];
            }

            if ($storedFromRow > 0) {
                $this->refreshActivityBoundaries($process, $row);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    private function actionAlreadyExists(string $processId, PrivateProcessExcelImportedRowDTO $row): bool
    {
        $registrationDate = $row->registrationDate ?? $row->startDate ?? now()->format('Y-m-d');

        if ($this->actionExistsWithText($processId, $registrationDate, $row->actionText, $row->annotation)) {
            return true;
        }

        $parts = $this->fijacionEstadoActionSplitter->split($row->actionText);
        if (count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (! $this->actionExistsWithText($processId, $registrationDate, $part, $row->annotation)) {
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

    private function takeNextNegativeActionRegistrationId(): int
    {
        $id = $this->actionRegistrationSeed;
        $this->actionRegistrationSeed--;

        return $id;
    }

    private function refreshActivityBoundaries(Process $process, PrivateProcessExcelImportedRowDTO $row): void
    {
        $reg = $row->registrationDate;
        $actDate = $row->startDate ?? $reg;

        $process->refresh();

        $updates = [];

        if ($reg !== null) {
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

    // ─── Grouping helpers ────────────────────────────────────────────────────

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

    // ─── Batch persistence ───────────────────────────────────────────────────

    /**
     * @param  array<string, list<PrivateProcessExcelImportedRowDTO>>  $grouped
     */
    private function persistBatchCompleted(
        ?string $requestedByUserId,
        string $fileName,
        PrivateProcessExcelParseResult $parsed,
        array $grouped,
        int $actionsImported,
    ): ProcessImportBatch {
        $excelRows = count($parsed->rows);

        return ProcessImportBatch::query()->create([
            'organization_id' => null,
            'requested_by' => $requestedByUserId,
            'file_name' => $fileName,
            'is_private_import' => true,
            'excel_total_count' => $excelRows,
            'total_count' => count($grouped),
            'enqueued_process_numbers' => $this->orderedUniqueRadicados($parsed->rows),
            'success_count' => $actionsImported,
            'failed_count' => max(0, $excelRows - $actionsImported),
            'multiple_instances_count' => 0,
            'status' => ProcessImportBatch::STATUS_COMPLETED,
            'errors' => [],
            'laravel_batch_id' => null,
            'completed_at' => now(),
        ]);
    }

    private function persistBatchFailedFromRowErrors(
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

        return $this->persistBatchFailed(
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
    private function persistBatchFailed(
        ?string $requestedByUserId,
        string $fileName,
        int $excelTotalCount,
        int $failedCount,
        array $errors,
    ): ProcessImportBatch {
        return ProcessImportBatch::query()->create([
            'organization_id' => null,
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
}
