<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\Data\StoreProcessData;
use Src\Application\AppUser\Process\Jobs\SyncSamaiJob;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Throwable;

/**
 * Despacha el registro SAMAI a la cola cuando el historial de actuaciones es muy largo.
 * Espejo de DispatchProcessRegistrationService para la fuente SAMAI.
 */
class DispatchSamaiProcessRegistrationService
{
    /**
     * @throws Throwable
     */
    public function handle(StoreProcessData $data, Organization $organization, AppUser $appUser): void
    {
        $organization = Organization::query()
            ->where('id', $organization->id)
            ->where('is_active', true)
            ->firstOrFail();

        DB::transaction(function () use ($data, $organization, $appUser): void {
            ProcessRegistrationLog::query()->create([
                'organization_id' => $organization->id,
                'app_user_id' => $appUser->id,
                'process_number' => $data->process_number,
                'status' => 'pending',
            ]);

            dispatch(new SyncSamaiJob(
                $data->process_number,
                $organization->id,
                $appUser,
                $data->lawyer_role,
            ))->onQueue(config('judicial-sync.jobs.sync_process.queue'));
        });
    }
}
