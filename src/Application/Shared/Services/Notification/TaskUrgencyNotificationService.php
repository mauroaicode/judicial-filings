<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Application\Shared\Contracts\Notification\TaskUrgencyChannelDriverInterface;
use Src\Application\Shared\DTOs\TaskUrgencyAlert;
use Src\Application\Shared\Services\Notification\Channels\EmailTaskUrgencyChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\InternalTaskUrgencyChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\SmsTaskUrgencyChannelDriver;
use Src\Application\Shared\Services\Notification\Channels\WhatsAppTaskUrgencyChannelDriver;
use Src\Domain\Notification\Models\HistoryOrganizationChannelNotification;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Throwable;

/**
 * Orchestrates task urgency alerts across organization notification channels.
 *
 * Extensibility: add a new entry to CHANNEL_DRIVERS to support additional channels.
 */
class TaskUrgencyNotificationService
{
    /**
     * @var array<string, class-string<TaskUrgencyChannelDriverInterface>>
     */
    private const CHANNEL_DRIVERS = [
        'email' => EmailTaskUrgencyChannelDriver::class,
        'whatsapp' => WhatsAppTaskUrgencyChannelDriver::class,
        'sms' => SmsTaskUrgencyChannelDriver::class,
    ];

    public function __construct(
        private readonly InternalTaskUrgencyChannelDriver $internalDriver,
    ) {}

    /**
     * Dispatches the alert through internal + active external channels.
     *
     * @return bool True when at least one channel succeeded
     */
    public function notify(TaskUrgencyAlert $alert): bool
    {
        $organizationId = $alert->organizationId();
        $orgNotification = $this->createOrganizationNotification($alert);

        $atLeastOneSuccess = $this->dispatchInternalChannel($alert, $orgNotification, $organizationId);

        $activeChannels = OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereIn('channel_type', array_keys(self::CHANNEL_DRIVERS))
            ->orderBy('priority')
            ->get();

        foreach ($activeChannels as $channel) {
            if ($this->dispatchChannel($alert, $orgNotification, $channel)) {
                $atLeastOneSuccess = true;
            }
        }

        if ($atLeastOneSuccess) {
            $this->markOrganizationNotified($orgNotification);
        }

        return $atLeastOneSuccess;
    }

    private function createOrganizationNotification(TaskUrgencyAlert $alert): OrganizationNotification
    {
        $keys = [
            'organization_id' => $alert->organizationId(),
            'notifiable_id' => $alert->task->id,
            'notifiable_type' => $alert->task->getMorphClass(),
            'notification_type' => $alert->notificationType(),
        ];

        $orgNotification = OrganizationNotification::query()->firstOrCreate(
            $keys,
            [
                'id' => (string) Str::uuid(),
                'severity_color' => $alert->urgencyLevel->value,
                'is_notified' => false,
                'is_viewed' => false,
            ],
        );

        if (! $orgNotification->wasRecentlyCreated) {
            OrganizationNotification::query()
                ->where($keys)
                ->update([
                    'severity_color' => $alert->urgencyLevel->value,
                    'is_notified' => false,
                    'is_viewed' => false,
                    'notified_at' => null,
                ]);
        }

        return OrganizationNotification::query()->where($keys)->firstOrFail();
    }

    private function dispatchInternalChannel(
        TaskUrgencyAlert $alert,
        OrganizationNotification $orgNotification,
        string $organizationId,
    ): bool {
        try {
            $this->internalDriver->send($alert, $organizationId);

            $internalChannel = $this->findInternalChannel($organizationId);

            if ($internalChannel instanceof OrganizationNotificationChannel) {
                $this->recordHistory($orgNotification, $internalChannel, true);
            }

            return true;
        } catch (Throwable $e) {
            $this->log('Internal task urgency channel failed', $alert, ['error' => $e->getMessage()], 'error');

            return false;
        }
    }

    private function dispatchChannel(
        TaskUrgencyAlert $alert,
        OrganizationNotification $orgNotification,
        OrganizationNotificationChannel $channel,
    ): bool {
        $driverClass = self::CHANNEL_DRIVERS[$channel->channel_type] ?? null;

        if ($driverClass === null) {
            return false;
        }

        try {
            /** @var TaskUrgencyChannelDriverInterface $driver */
            $driver = resolve($driverClass);
            $driver->send($alert, $channel);

            $this->recordHistory($orgNotification, $channel, true);

            return true;
        } catch (Throwable $e) {
            $this->recordHistory($orgNotification, $channel, false);

            $this->log('Task urgency channel dispatch failed', $alert, [
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel_type,
                'error' => $e->getMessage(),
            ], 'error');

            return false;
        }
    }

    private function recordHistory(
        OrganizationNotification $orgNotification,
        OrganizationNotificationChannel $channel,
        bool $success,
    ): void {
        HistoryOrganizationChannelNotification::query()->create([
            'organization_notification_channel_id' => $channel->id,
            'notifiable_id' => $orgNotification->notifiable_id,
            'notifiable_type' => $orgNotification->notifiable_type,
            'notification_type' => $orgNotification->notification_type,
            'is_notified' => $success,
            'notified_at' => $success ? now() : null,
        ]);
    }

    private function markOrganizationNotified(OrganizationNotification $orgNotification): void
    {
        $orgNotification->update([
            'is_notified' => true,
            'notified_at' => now(),
        ]);
    }

    private function findInternalChannel(string $organizationId): ?OrganizationNotificationChannel
    {
        return OrganizationNotificationChannel::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', 'internal')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, TaskUrgencyAlert $alert, array $context = [], string $level = 'info'): void
    {
        Log::channel(config('tasks.log_channel', 'stack'))
            ->$level($message, array_merge([
                'task_id' => $alert->task->id,
                'organization_id' => $alert->organizationId(),
                'urgency_level' => $alert->urgencyLevel->value,
                'days_elapsed' => $alert->daysElapsed,
            ], $context));
    }
}
