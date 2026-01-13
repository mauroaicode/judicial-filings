<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Services;

use Core\BoundedContext\Customer\Process\Domain\Repositories\OrganizationNotificationChannelRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Jobs\SendChannelNotificationJob;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels\EmailNotificationChannel;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels\InternalNotificationChannel;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels\SmsNotificationChannel;
use Core\BoundedContext\Customer\Process\Infrastructure\Notifications\Channels\WhatsAppNotificationChannel;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for dispatching individual notification jobs per channel
 */
readonly class ChannelNotificationDispatcherService
{
    public function __construct(
        private OrganizationNotificationChannelRepositoryInterface $repository
    ) {}

    /**
     * Dispatch individual notification jobs for each active channel
     */
    public function dispatchNotificationsByChannel(
        string $notificationType,
        array $processData,
        array $organizationData,
        array $additionalData = [],
        int $baseDelaySeconds = 4
    ): array {
        $organization = Organization::query()->find($organizationData['id']);

        if (!$organization) {
            Log::channel('notifications')->error('Organización no encontrada para despachar notificaciones', [
                'organization_id' => $organizationData['id'] ?? 'N/A',
                'notification_type' => $notificationType,
            ]);
            return [];
        }

        $dispatchedJobs = [];
        $totalChannels = 0;

        foreach (NotificationChannelType::getActiveChannels() as $channelType) {
            $activeChannels = $this->repository->getActiveChannelsByType($organization, $channelType);

            if ($activeChannels->isEmpty()) {
                Log::channel('notifications')->warning('No hay canales activos para este tipo', [
                    'channel_type' => $channelType->value,
                    'organization_id' => $organization->id,
                ]);
                continue;
            }

            $totalChannels += $activeChannels->count();

            $channelJobs = $this->dispatchJobsForChannelType(
                $notificationType,
                $processData,
                $organizationData,
                $additionalData,
                $channelType,
                $activeChannels,
                $baseDelaySeconds
            );

            $dispatchedJobs = array_merge($dispatchedJobs, $channelJobs);
        }

        Log::channel('notifications')->info('Jobs de notificación despachados por canal', [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'notification_type' => $notificationType,
            'total_channels' => $totalChannels,
            'total_jobs_dispatched' => count($dispatchedJobs),
            'base_delay_seconds' => $baseDelaySeconds,
        ]);

        return $dispatchedJobs;
    }

    /**
     * Dispatch jobs for a specific channel type
     */
    private function dispatchJobsForChannelType(
        string                  $notificationType,
        array                   $processData,
        array                   $organizationData,
        array                   $additionalData,
        NotificationChannelType $channelType,
        Collection              $activeChannels,
        int                     $baseDelaySeconds
    ): array {
        $channelClass = $this->getChannelClass($channelType);
        $dispatchedJobs = [];

        foreach ($activeChannels as $channel) {
            // Increase delay for email channels to respect rate limits
            $isEmailChannel = $channel->channel_type === 'email';
            $baseDelay = $isEmailChannel ? $baseDelaySeconds * 3 : $baseDelaySeconds; // Triple delay for emails
            $delaySeconds = $baseDelay + ($channel->priority - 1) * ($isEmailChannel ? 5 : 2); // 5 seconds between emails


            SendChannelNotificationJob::dispatch(
                $notificationType,
                $processData,
                $organizationData,
                $additionalData,
                $channelClass,
                $channel->channel_value,
                $channel->priority,
                $delaySeconds
            );

            $dispatchedJobs[] = [
                'job_id' => uniqid('job_', true),
                'channel_type' => $channelType->value,
                'channel_value' => $channel->channel_value,
                'priority' => $channel->priority,
                'delay_seconds' => $delaySeconds,
                'queue' => config('queue.queues.notifications.queue', 'notifications'),
            ];

            Log::channel('notifications')->info('Job de notificación despachado por canal individual', [
                'job_id' => uniqid('job_', true),
                'channel_type' => $channelType->value,
                'channel_value' => $channel->channel_value,
                'priority' => $channel->priority,
                'delay_seconds' => $delaySeconds,
                'organization_id' => $organizationData['id'],
                'notification_type' => $notificationType,
            ]);
        }

        return $dispatchedJobs;
    }

    /**
     * Get the channel class for a specific channel type
     */
    private function getChannelClass(NotificationChannelType $channelType): string
    {
        return match ($channelType) {
            NotificationChannelType::EMAIL => EmailNotificationChannel::class,
            NotificationChannelType::WHATSAPP => WhatsAppNotificationChannel::class,
            NotificationChannelType::SMS => SmsNotificationChannel::class,
            NotificationChannelType::INTERNAL => InternalNotificationChannel::class,
        };
    }

    /**
     * Dispatch notifications for multiple organizations
     */
    public function dispatchNotificationsForMultipleOrganizations(
        string $notificationType,
        array $processData,
        array $organizationsData,
        array $additionalData = [],
        int $baseDelaySeconds = 4
    ): array {
        $allDispatchedJobs = [];
        $totalOrganizations = count($organizationsData);


        foreach ($organizationsData as $index => $organizationData) {
            $organizationDelay = $baseDelaySeconds + ($index * config('queue.queues.notifications.delay_for_organization'));

            $jobs = $this->dispatchNotificationsByChannel(
                $notificationType,
                $processData,
                $organizationData,
                $additionalData,
                $organizationDelay
            );

            $allDispatchedJobs = array_merge($allDispatchedJobs, $jobs);
        }

        Log::channel('notifications')->info('Notificaciones despachadas para todas las organizaciones', [
            'notification_type' => $notificationType,
            'total_organizations' => $totalOrganizations,
            'total_jobs_dispatched' => count($allDispatchedJobs),
        ]);

        return $allDispatchedJobs;
    }

    /**
     * Get notification statistics for dispatching
     */
    public function getNotificationDispatchStats(
        array $organizationsData,
        NotificationChannelType $channelType = null
    ): array {
        $stats = [
            'total_organizations' => count($organizationsData),
            'channels_by_type' => [],
            'total_channels' => 0,
            'estimated_jobs' => 0,
        ];

        foreach ($organizationsData as $organizationData) {
            $organization = Organization::query()->find($organizationData['id']);

            if (!$organization) {
                continue;
            }

            if ($channelType) {
                $channels = $this->repository->getActiveChannelsByType($organization, $channelType);
                $stats['channels_by_type'][$channelType->value] = ($stats['channels_by_type'][$channelType->value] ?? 0) + $channels->count();
            } else {
                $allChannels = $this->repository->getAllActiveChannels($organization);
                $stats['total_channels'] += $allChannels->count();

                foreach ($allChannels as $channel) {
                    $stats['channels_by_type'][$channel->channel_type] = ($stats['channels_by_type'][$channel->channel_type] ?? 0) + 1;
                }
            }
        }

        $stats['estimated_jobs'] = array_sum($stats['channels_by_type']);

        return $stats;
    }
}
