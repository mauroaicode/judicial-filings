<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Services;

use Core\BoundedContext\Customer\Process\Application\Actions\{
    CreateOrUpdateProcessUseCase
};
use Core\BoundedContext\Customer\Process\Domain\Repositories\{
    ProcessRepositoryInterface,
    OrganizationNotificationRepositoryInterface
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Services\{
    JudicialBranchConsultService,
    ChannelNotificationDispatcherService
};
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Core\BoundedContext\Customer\Process\Application\Traits\ProcessCompleteDataTrait;

readonly class MultipleInstancesHandlerService
{
    use ProcessCompleteDataTrait;

    public function __construct(
        private ProcessRepositoryInterface                  $processRepository,
        private JudicialBranchConsultService                $judicialService,
        private CreateOrUpdateProcessUseCase                $createOrUpdateProcessUseCase,
        private OrganizationNotificationRepositoryInterface $organizationNotificationRepository,
        private ChannelNotificationDispatcherService        $channelDispatcher,

    ){
    }

    /**
     * Handles the main logic for processes with multiple instances
     *
     * @param string $filingNumber The filing number to process
     * @param array $processes Array of processes from the judicial API
     * @param Collection $interestedOrganizations Organizations interested in this filing
     */
    public function handle(string $filingNumber, array $processes, Collection $interestedOrganizations): void
    {
        if ($interestedOrganizations->isEmpty()) {
            Log::channel('judicial_process_chunk_job')->info("No hay organizaciones interesadas para el radicado {$filingNumber}");
            return;
        }

        $organizationIds = $interestedOrganizations->pluck('id')->toArray();


        $organizationsToNotify = $this->organizationNotificationRepository->getOrganizationsNotNotifiedMultipleInstances(
            $filingNumber,
            NotificationType::MULTIPLE_INSTANCE->value,
            $organizationIds
        );

        if (empty($organizationsToNotify)) {
            $this->checkForNewInstancesAndNotify($filingNumber, $processes, $interestedOrganizations, $organizationsToNotify);
        } else {
            $organizationsToNotifyCollection = $interestedOrganizations->whereIn('id', $organizationsToNotify);
            $this->processAndNotifyMultipleInstances($filingNumber, $processes, $organizationsToNotifyCollection);
        }
    }

    /**
     * Checks for new instances and sends notifications if necessary
     *
     * @param string $filingNumber The filing number to check
     * @param array $processes Array of processes from the judicial API
     * @param Collection $interestedOrganizations Organizations interested in this filing
     * @param array $organizationsToNotify Organizations that haven't been notified yet
     */
    private function checkForNewInstancesAndNotify(string $filingNumber, array $processes, Collection $interestedOrganizations, array $organizationsToNotify): void
    {
        $existingProcessIds = $this->organizationNotificationRepository->getExistingProcessIds($filingNumber);
        $newApiProcessIds = collect($processes)->pluck('idProceso')->toArray();

        $newInstances = array_diff($newApiProcessIds, $existingProcessIds);

        if (!empty($newInstances)) {
            if (!empty($organizationsToNotify)) {
                $organizationsToNotifyCollection = $interestedOrganizations->whereIn('id', $organizationsToNotify);
                $this->processAndNotifyMultipleInstances($filingNumber, $processes, $organizationsToNotifyCollection);
            }
        }
    }

    /**
     * Processes multiple instances and sends notifications to interested organizations
     *
     * @param string $filingNumber The filing number to process
     * @param array $processes Array of processes from the judicial API
     * @param Collection $interestedOrganizations Organizations to notify
     */
    private function processAndNotifyMultipleInstances(string $filingNumber, array $processes, Collection $interestedOrganizations): void
    {
        $organizationIds = $interestedOrganizations->pluck('id')->toArray();
        $organizationsData = $interestedOrganizations->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'type' => $org->type,
            ];
        })->toArray();

        $createdProcesses = [];

        foreach ($processes as $processBasic) {
            $unifiedProcess = $this->getCompleteProcessData($processBasic, $filingNumber, $this->judicialService);

            if ($unifiedProcess) {
                $process = $this->createOrUpdateProcessUseCase->__invoke($unifiedProcess);
                $createdProcesses[] = $process;

                $this->processRepository->assignOrganizationsToProcess($process->id, $organizationIds);
            }
        }

        $this->processRepository->updateProcessesByProcessNumber($filingNumber, ['has_multiple_instances' => true]);

        if (!empty($createdProcesses)) {
            $this->dispatchMultipleInstancesNotification($organizationsData,$filingNumber);
        }
    }


    /**
     * Dispatches multiple instances notifications using channel-specific jobs
     *
     * @param array $organizationsData Organizations data to notify
     * @param string $filingNumber The filing number for the notification
     */
    private function dispatchMultipleInstancesNotification(array $organizationsData, string $filingNumber): void
    {
        try {
            // Get the first process to use as reference for process data
            $firstProcess = $this->processRepository->findByProcessNumber($filingNumber)->first();

            if (!$firstProcess) {
                Log::channel('judicial_process_chunk_job')->error("No process found for filing number {$filingNumber}");
                return;
            }

            $processData = [
                'id' => $firstProcess->id,
                'process_number' => $firstProcess->process_number,
                'court' => $firstProcess->court,
                'department' => $firstProcess->department,
                'process_type' => $firstProcess->process_type,
                'process_class' => $firstProcess->process_class,
            ];

            $additionalData = [
                'filing_number' => $filingNumber,
                'detected_at' => now()->format('d/m/Y H:i:s'),
            ];

            // Create notification records before dispatching
            $this->createNotificationRecords($filingNumber, $organizationsData, $firstProcess);

            $this->channelDispatcher->dispatchNotificationsForMultipleOrganizations(
                NotificationType::MULTIPLE_INSTANCE->value,
                $processData,
                $organizationsData,
                $additionalData,
                4 // Base delay of 4 seconds
            );


        } catch (\Exception $e) {
            Log::channel('judicial_process_chunk_job')->error("Error dispatching channel notifications for filing {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Creates notification records for organizations before dispatching notifications
     *
     * @param string $filingNumber The filing number
     * @param array $organizationsData Organizations to create notifications for
     * @param object $process The process object for reference
     */
    private function createNotificationRecords(string $filingNumber, array $organizationsData, $process): void
    {
        try {
            $organizationIds = array_column($organizationsData, 'id');
            
            $this->organizationNotificationRepository->createNotificationRecordsForOrganizations(
                $process->id,
                Process::class,
                NotificationType::MULTIPLE_INSTANCE->value,
                $organizationIds
            );

            Log::channel('judicial_process_chunk_job')->info("Notification records created for filing {$filingNumber}", [
                'organizations_count' => count($organizationIds),
                'filing_number' => $filingNumber,
                'notification_type' => NotificationType::MULTIPLE_INSTANCE->value
            ]);

        } catch (\Exception $e) {
            Log::channel('judicial_process_chunk_job')->error("Error creating notification records for filing {$filingNumber}: " . $e->getMessage(), [
                'filing_number' => $filingNumber,
                'error' => $e->getMessage()
            ]);
        }
    }
}
