<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\AppUser\Process\Services\RegisterProcessService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Notifications\ProcessDataImportedNotification;
use Src\Domain\Notification\Notifications\ProcessImportFailedNotification;
use Src\Domain\Process\Models\ProcessRegistrationLog;
use Throwable;

class SyncJudicialBranchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;

    public function __construct(
        public string $processNumber,
        public string $organizationId,
        public AppUser $appUser
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(RegisterProcessService $registerProcessService): void
    {
        try {

            $result = $registerProcessService->handle(
                $this->processNumber,
                $this->organizationId,
                $this->processNumber
            );

            $process = $result->getFirstProcess();

            if ($process) {

                $this->updateLogStatus('success');

                $this->appUser->notify(new ProcessDataImportedNotification($process));

                if (config('ia-rag.enabled')) {
                    GenerateProcessAiSummaryJob::dispatch($process, $this->organizationId, $this->appUser)
                        ->onQueue(config('ia-rag.queues.ai'));
                }
            } else {
                $this->updateLogStatus('failed', 'No process was imported.');
            }
        } catch (Throwable $e) {

            $this->updateLogStatus('failed', $e->getMessage());

            $this->appUser->notify(new ProcessImportFailedNotification($this->processNumber, $e->getMessage()));

            throw $e;
        }
    }

    private function updateLogStatus(string $status, ?string $error = null): void
    {
        ProcessRegistrationLog::query()
            ->where('process_number', $this->processNumber)
            ->where('organization_id', $this->organizationId)
            ->where('app_user_id', $this->appUser->id)
            ->where('status', 'pending')
            ->latest()
            ->first()
            ?->update([
                'status' => $status,
                'error' => $error,
            ]);
    }
}
