<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Shared\CourtDirectory\Services\QueueCourtDirectorySyncService;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Throwable;

class SyncCourtDirectoryCommand extends Command
{
    protected $signature = 'court-directory:sync';

    protected $description = 'Encola la importación del directorio público de correos de los despachos judiciales';

    public function handle(QueueCourtDirectorySyncService $queueCourtDirectorySyncService): int
    {
        try {
            $run = $queueCourtDirectorySyncService->handle(CourtDirectorySyncTrigger::Command);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($run->status === CourtDirectorySyncStatus::Failed) {
            $this->error($run->error ?? 'La sincronización del directorio falló.');

            return self::FAILURE;
        }

        if ($run->status === CourtDirectorySyncStatus::Succeeded) {
            $this->info("Directorio actualizado: {$run->rows_upserted} correos, {$run->rows_marked_inactive} inactivos.");

            return self::SUCCESS;
        }

        $this->info('Sincronización encolada en '.config('court-directory.queue').". Corrida {$run->id}.");

        return self::SUCCESS;
    }
}
