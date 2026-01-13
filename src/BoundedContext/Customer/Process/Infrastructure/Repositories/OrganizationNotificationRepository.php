<?php

namespace Core\BoundedContext\Customer\Process\Infrastructure\Repositories;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotification;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrganizationNotificationRepository implements OrganizationNotificationRepositoryInterface
{
    public function hasOrganizationBeenNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): bool
    {
        return OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->where('is_notified', true)
            ->exists();
    }

    public function getOrganizationsNotNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_notified', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    public function markOrganizationAsNotified(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationType,
            ],
            [
                'is_notified' => true,
                'notified_at' => now(),
            ]
        );
    }

    public function markOrganizationsAsNotified(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): void
    {
        foreach ($organizationIds as $organizationId) {
            $this->markOrganizationAsNotified($organizationId, $notifiableType, $notifiableId, $notificationType);
        }
    }

    public function markOrganizationAsViewed(string $organizationId, string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationType,
            ],
            [
                'is_viewed' => true,
                'viewed_at' => now(),
            ]
        );
    }

    public function getOrganizationsNotViewed(string $notifiableType, string $notifiableId, string $notificationType, array $organizationIds): array
    {
        $viewedOrganizationIds = OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_viewed', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $viewedOrganizationIds);
    }

    public function getNotificationHistory(string $notifiableType, string $notifiableId, string $notificationType): Collection
    {
        return OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->with('organization')
            ->get();
    }

    public function getAllNotificationRecords(): Collection
    {
        return OrganizationNotification::query()
            ->with(['organization', 'notifiable'])
            ->get();
    }

    public function cleanFailedNotifications(string $notifiableType, string $notifiableId, string $notificationType): void
    {
        OrganizationNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->where('is_notified', false)
            ->delete();
    }

    public function hasAlreadyNotifiedMultipleInstances(string $processNumber, string $notificationType): bool
    {
        return Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('notifications', function ($query) use ($notificationType) {
                $query->where('notification_type', $notificationType)
                    ->where('is_notified', true);
            })
            ->exists();
    }

    public function getOrganizationsNotNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('notifications', function ($query) use ($notificationType) {
                $query->where('notification_type', $notificationType)
                    ->where('is_notified', true);
            })
            ->with('notifications')
            ->get()
            ->flatMap(function ($process) use ($notificationType) {
                return $process->notifications
                    ->where('notification_type', $notificationType)
                    ->where('is_notified', true)
                    ->pluck('organization_id');
            })
            ->unique()
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    public function markOrganizationsAsNotifiedByProcessNumber(string $processNumber, string $notificationType, array $organizationIds): void
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->get();

        foreach ($processes as $process) {
            foreach ($organizationIds as $organizationId) {
                $this->markOrganizationAsNotified(
                    $organizationId,
                    Process::class,
                    $process->id,
                    $notificationType
                );
            }
        }
    }

    public function getOrganizationsNotNotifiedByProcessId(string $notifiableId, string $notificationType, array $organizationIds): array
    {
        // Este método ahora funciona tanto para Process como para ProcessAction
        $notifiedOrganizationIds = OrganizationNotification::query()
            ->where('notifiable_id', $notifiableId)
            ->where('notification_type', $notificationType)
            ->whereIn('organization_id', $organizationIds)
            ->where('is_notified', true)
            ->pluck('organization_id')
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }

    /**
     * Create notification records for multiple organizations with is_notified = false
     * This allows for retry mechanisms in case of failures
     */
    public function createNotificationRecordsForOrganizations(
        string $notifiableId,
        string $notifiableType,
        string $notificationType,
        array  $organizationIds
    ): void
    {
        $createdCount = 0;
        $updatedCount = 0;

        Log::channel('judicial_process_sync_job')->info("🗄️ REPOSITORIO: Creando registros de notificación", [
            'notifiable_id' => $notifiableId,
            'notifiable_type' => $notifiableType,
            'notification_type' => $notificationType,
            'organizations_count' => count($organizationIds),
            'organization_ids' => $organizationIds
        ]);

        foreach ($organizationIds as $organizationId) {
            $result = OrganizationNotification::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'notifiable_id' => $notifiableId,
                    'notifiable_type' => $notifiableType,
                    'notification_type' => $notificationType,
                ],
                [
                    'is_viewed' => false,
                    'is_notified' => false, // Important: false to allow retries
                    'viewed_at' => null,
                    'notified_at' => null,
                ]
            );

            if ($result->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }

            Log::channel('judicial_process_sync_job')->info("💾 REPOSITORIO: Registro creado/actualizado", [
                'organization_id' => $organizationId,
                'notifiable_id' => $notifiableId,
                'notification_type' => $notificationType,
                'record_id' => $result->id,
                'was_recently_created' => $result->wasRecentlyCreated
            ]);
        }

        Log::channel('judicial_process_sync_job')->info("✅ REPOSITORIO: Registros procesados - RESUMEN", [
            'notifiable_id' => $notifiableId,
            'notification_type' => $notificationType,
            'total_organizations' => count($organizationIds),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'total_processed' => $createdCount + $updatedCount
        ]);
    }

    /**
     * Create notification records for organizations interested in a specific process number
     */
    public function createNotificationRecordsForProcessNumber(
        string $processNumber,
        string $notificationType,
        array  $organizationIds
    ): void
    {
        $processes = Process::query()
            ->where('process_number', $processNumber)
            ->get();

        foreach ($processes as $process) {
            $this->createNotificationRecordsForOrganizations(
                $process->id,
                Process::class,
                $notificationType,
                $organizationIds
            );
        }
    }

    /**
     * Get existing process IDs for a given process number
     */
    public function getExistingProcessIds(string $processNumber): array
    {
        return Process::query()
            ->where('process_number', $processNumber)
            ->pluck('process_id')
            ->toArray();
    }

    /**
     * Get organizations that haven't been notified about multiple instances for a specific process number
     */
    public function getOrganizationsNotNotifiedMultipleInstances(string $processNumber, string $notificationType, array $organizationIds): array
    {
        $notifiedOrganizationIds = Process::query()
            ->where('process_number', $processNumber)
            ->whereHas('notifications', function ($query) use ($notificationType) {
                $query->where('notification_type', $notificationType)
                    ->where('is_notified', true);
            })
            ->with('notifications')
            ->get()
            ->flatMap(function ($process) use ($notificationType) {
                return $process->notifications
                    ->where('notification_type', $notificationType)
                    ->where('is_notified', true)
                    ->pluck('organization_id');
            })
            ->unique()
            ->toArray();

        return array_diff($organizationIds, $notifiedOrganizationIds);
    }
}
