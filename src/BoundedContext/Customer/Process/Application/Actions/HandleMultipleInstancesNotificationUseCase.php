<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Actions;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Core\Shared\Infrastructure\Services\ChannelNotificationDispatcherService;
use Core\Shared\Domain\Enums\NotificationType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

readonly class HandleMultipleInstancesNotificationUseCase
{
    public function __construct(
        private OrganizationNotificationRepositoryInterface $notificationRepository,
        private ChannelNotificationDispatcherService $channelDispatcher
    ){
    }

    /**
     * Maneja las notificaciones para procesos con múltiples instancias
     */
    public function __invoke(Process $process, Collection $interestedOrganizations, string $filingNumber): void
    {
        try {
            Log::channel('judicial_process_sync_job')->info("Verificando notificaciones para radicado {$filingNumber} con múltiples instancias");

            $organizationIds = $interestedOrganizations->pluck('id')->toArray();

            $organizationsToNotify = $this->notificationRepository->getOrganizationsNotNotifiedByProcessNumber(
                $filingNumber,
                NotificationType::MULTIPLE_INSTANCE->value,
                $organizationIds
            );

            Log::channel('judicial_process_sync_job')->info("Organizaciones a notificar: " . json_encode($organizationsToNotify));

            if (empty($organizationsToNotify)) {
                Log::channel('judicial_process_sync_job')->info("Todas las organizaciones ya fueron notificadas para el radicado {$filingNumber}");
                return;
            }

            Log::channel('judicial_process_sync_job')->info("Enviando notificaciones a " . count($organizationsToNotify) . " organizaciones para radicado {$filingNumber}");

            $this->dispatchMultipleInstancesNotification($process, $organizationsToNotify, $filingNumber);

            Log::channel('judicial_process_sync_job')->info("Job de notificación despachado para radicado {$filingNumber}. Se marcará como notificado solo si se envía exitosamente.");

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error manejando notificaciones para radicado {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $process->id,
                'filing_number' => $filingNumber,
            ]);
        }
    }

    /**
     * Despacha la notificación de múltiples instancias usando jobs por canal
     */
    private function dispatchMultipleInstancesNotification(Process $process, array $organizationIds, string $filingNumber): void
    {
        try {

            $processData = [
                'id' => $process->id,
                'process_number' => $process->process_number,
                'court' => $process->court,
                'department' => $process->department,
                'process_type' => $process->process_type,
                'process_class' => $process->process_class,
            ];

            $additionalData = [
                'filing_number' => $filingNumber,
                'detected_at' => now()->format('d/m/Y H:i:s'),
                'notification_key' => $filingNumber,
                'process_id' => $process->id,
            ];


            $organizationsData = [];
            foreach ($organizationIds as $orgId) {
                $org = Organization::query()->find($orgId);
                if ($org) {
                    $organizationsData[] = [
                        'id' => $org->id,
                        'name' => $org->name,
                        'slug' => $org->slug,
                        'type' => $org->type,
                    ];
                }
            }

            $dispatchedJobs = $this->channelDispatcher->dispatchNotificationsForMultipleOrganizations(
                NotificationType::MULTIPLE_INSTANCE->value,
                $processData,
                $organizationsData,
                $additionalData,
                4 // Base delay de 4 segundos
            );

            Log::channel('judicial_process_sync_job')->info("Jobs de notificación por canal despachados para radicado {$filingNumber}", [
                'total_jobs_dispatched' => count($dispatchedJobs),
                'organizations_count' => count($organizationsData),
                'filing_number' => $filingNumber,
            ]);

        } catch (\Exception $e) {
            Log::channel('judicial_process_sync_job')->error("Error despachando notificaciones por canal para radicado {$filingNumber}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
