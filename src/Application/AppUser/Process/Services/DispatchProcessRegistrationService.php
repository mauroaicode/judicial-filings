<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Services;

use Illuminate\Support\Facades\DB;
use Src\Application\AppUser\Process\Data\StoreProcessData;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Throwable;

class DispatchProcessRegistrationService
{
    /**
     * Dispatches the process registration flow asynchronously.
     *
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

            dispatch(new \Src\Application\AppUser\Process\Jobs\SyncJudicialBranchJob($data->process_number, $organization->id, $appUser, $data->lawyer_role))->onQueue(config('judicial-sync.jobs.sync_process.queue'));
        });
    }
}
