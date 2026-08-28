<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;

/**
 * Result of importing actuaciones from a manual Excel upload.
 *
 * Radicados without a Process are stored in
 * {@see \Src\Domain\Process\Models\UnassignedProcessAction} and listed in
 * {@see $unassigned_process_numbers}. Existing processes matched in DB are listed in
 * {@see $processes_updated_numbers}. Duplicates are listed in {@see $skipped_actions}.
 */
class ProcessActuacionesImportResource extends Resource
{
    /**
     * @param  list<string>  $unassigned_process_numbers
     * @param  list<string>  $processes_updated_numbers
     * @param  list<ProcessActuacionesSkippedItemResource>  $skipped_actions
     */
    public function __construct(
        public int $actions_imported,
        public int $actions_skipped,
        public int $actions_stored_unassigned,
        public int $processes_updated,
        public array $processes_updated_numbers,
        public int $unassigned_count,
        public array $unassigned_process_numbers,
        public array $skipped_actions,
        public string $import_batch_id,
    ) {}
}
