<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;

/**
 * Result of importing actuaciones from a manual Excel upload.
 *
 * Radicados without a Process are no longer discarded: their actuaciones are
 * stored in {@see \Src\Domain\Process\Models\UnassignedProcessAction} and listed
 * in {@see $unassigned_process_numbers} for transparency.
 */
class ProcessActuacionesImportResource extends Resource
{
    /**
     * @param  list<string>  $unassigned_process_numbers  Radicados whose actuaciones were stored in the historical repository (no Process yet).
     */
    public function __construct(
        public int $actions_imported,
        public int $actions_skipped,
        public int $actions_stored_unassigned,
        public int $processes_updated,
        public int $unassigned_count,
        public array $unassigned_process_numbers,
        public string $import_batch_id,
    ) {}
}
