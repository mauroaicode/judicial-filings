<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;
use Src\Domain\Process\Models\UnassignedProcessAction;
use Src\Domain\Process\Services\FijacionEstadoActionSplitter;

/**
 * Persists actuaciones from Excel when no Process exists yet for the radicado.
 * These rows are later attached via {@see AttachUnassignedProcessActionsService}.
 *
 * Combined titles like "Fijación Estado Auto …" are split into two rows. When the
 * estado half already exists for that radicado/date, it is skipped silently if the
 * Auto half is new — only true duplicates are reported to the user.
 */
class PersistUnassignedProcessActionsService
{
    public function __construct(
        private readonly FijacionEstadoActionSplitter $fijacionEstadoActionSplitter,
    ) {}

    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @return array{
     *     stored: int,
     *     skipped: int,
     *     process_numbers: list<string>,
     *     skipped_actions: list<array{
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
    public function handle(
        array $rows,
        ?string $importBatchId = null,
        ?string $importedByUserId = null,
    ): array {
        $stored = 0;
        $skipped = 0;
        /** @var array<string, true> $processNumbers */
        $processNumbers = [];
        /** @var list<array{process_number: string, action: string, annotation: string|null, registration_date: string|null, court: string|null, excel_row: int, reason: string, message: string}> $skippedActions */
        $skippedActions = [];

        foreach ($rows as $row) {
            if (trim($row->actionText) === '') {
                continue;
            }

            $actionTitles = $this->fijacionEstadoActionSplitter->split($row->actionText);
            if ($actionTitles === []) {
                continue;
            }

            $registrationDate = $row->registrationDate ?? $row->startDate ?? now()->format('Y-m-d');
            $isSplitPair = count($actionTitles) > 1;
            $storedFromRow = 0;
            $meaningfulSkips = 0;

            foreach ($actionTitles as $actionTitle) {
                $hash = UnassignedProcessAction::makeDedupeHash(
                    $row->processNumber,
                    $actionTitle,
                    $row->annotation,
                    $registrationDate,
                    $row->court,
                );

                $exists = UnassignedProcessAction::query()
                    ->whereProcessNumber($row->processNumber)
                    ->whereDedupeHash($hash)
                    ->exists();

                if ($exists) {
                    if ($isSplitPair && $this->fijacionEstadoActionSplitter->isEstadoPairLabel($actionTitle)) {
                        // Estado half already paired with a prior Auto for this radicado/date.
                        continue;
                    }

                    $meaningfulSkips++;

                    continue;
                }

                UnassignedProcessAction::query()->create([
                    'process_number' => $row->processNumber,
                    'court' => $row->court !== '' ? $row->court : null,
                    'process_class' => $row->processClass !== '' ? $row->processClass : null,
                    'plaintiffs_raw' => $row->plaintiffsRaw !== '' ? $row->plaintiffsRaw : null,
                    'defendants_raw' => $row->defendantsRaw !== '' ? $row->defendantsRaw : null,
                    'action' => $actionTitle,
                    'annotation' => $row->annotation,
                    'start_date' => $row->startDate,
                    'end_date' => $row->endDate,
                    'registration_date' => $registrationDate,
                    'dedupe_hash' => $hash,
                    'import_batch_id' => $importBatchId,
                    'imported_by' => $importedByUserId,
                ]);

                $stored++;
                $storedFromRow++;
                $processNumbers[$row->processNumber] = true;
            }

            if ($storedFromRow === 0 && $meaningfulSkips > 0) {
                $skipped++;
                $skippedActions[] = [
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
        }

        return [
            'stored' => $stored,
            'skipped' => $skipped,
            'process_numbers' => array_keys($processNumbers),
            'skipped_actions' => $skippedActions,
        ];
    }
}
