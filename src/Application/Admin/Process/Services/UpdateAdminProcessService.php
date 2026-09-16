<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Application\Admin\Process\Data\UpdateAdminProcessData;
use Src\Domain\Process\Models\Process;

readonly class UpdateAdminProcessService
{
    public function handle(string $processId, UpdateAdminProcessData $data): Process
    {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $updates = $data->toProcessUpdates();

        if ($updates !== []) {
            $process->update($updates);
        }

        return $process->refresh();
    }
}
