<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;

/**
 * Result of importing actuaciones from a manual Excel upload.
 *
 * Radicados present in the Excel but not found in the database are listed in
 * {@see $not_found_process_numbers} so the admin can create them first using
 * the "Importar Procesos" flow, and then re-import their actuaciones.
 */
class ProcessActuacionesImportResource extends Resource
{
    /**
     * @param  list<string>  $not_found_process_numbers  Radicados from the Excel that have no matching process in the database.
     */
    public function __construct(
        public int $actions_imported,
        public int $actions_skipped,
        public int $processes_updated,
        public int $not_found_count,
        public array $not_found_process_numbers,
        public string $import_batch_id,
    ) {}
}
