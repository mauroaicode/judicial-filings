<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * In-app + WebSocket alert for admin Users when a lawyer requests manual process registration.
 */
class ManualRegistrationRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ManualRegistrationRequest $request,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->getData();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->getData()))->onQueue('notifications');
    }

    public function broadcastType(): string
    {
        return 'ManualRegistrationRequested';
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(): array
    {
        $this->request->loadMissing(['organization', 'appUser']);

        $orgName = $this->request->organization?->name ?: '—';
        $user = $this->request->appUser;
        $userName = $user === null
            ? '—'
            : (trim($user->name.' '.$user->last_name) ?: '—');

        return [
            'title' => __('process.manual_registration_notification_title'),
            'description' => __('process.manual_registration_notification_description', [
                'number' => $this->request->process_number,
                'organization' => $orgName,
                'user' => $userName,
                'reason' => $this->request->reason->label(),
            ]),
            'type' => 'manual-registration-requested',
            'request_id' => $this->request->id,
            'process_number' => $this->request->process_number,
            'reason' => $this->request->reason->value,
            'organization_id' => $this->request->organization_id,
            'organization_name' => $orgName,
            'app_user_id' => $this->request->app_user_id,
            'unassigned_actions_count' => $this->request->unassigned_actions_count,
            'url' => '/admin/manual-registrations',
        ];
    }
}
